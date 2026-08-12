<?php

namespace App\Http\Controllers;

use App\Services\AuditStore;
use App\Services\CashStore;
use App\Services\CatalogStore;
use App\Services\LabResultStore;
use App\Services\OrderStore;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderStore $orders,
        private readonly CatalogStore $catalog,
        private readonly CashStore $cash,
        private readonly AuditStore $audit,
        private readonly LabResultStore $results,
    ) {}

    public function index(Request $request)
    {
        return view('orders.index', [
            'orders' => $this->orders->search($request->only('q', 'status', 'payment_status')),
            'filters' => $request->only('q', 'status', 'payment_status'),
        ]);
    }

    public function labQueue(Request $request)
    {
        $orders = $this->orders->search($request->only('q'))
            ->where('status', 'pending_results')
            ->values();

        return view('orders.lab-queue', [
            'readyOrders' => $orders->where('payment_status', 'paid')->values(),
            'partialOrders' => $orders->where('payment_status', 'partial')->values(),
            'unpaidOrders' => $orders->where('payment_status', 'unpaid')->values(),
            'filters' => $request->only('q'),
        ]);
    }

    public function create()
    {
        return view('orders.create', [
            'categories' => $this->catalog->categories(),
            'prices' => $this->catalog->prices(),
            'referrers' => $this->catalog->referrers(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'patient_name' => ['required', 'string', 'max:160'],
            'age' => ['nullable', 'string', 'max:40'],
            'phone' => ['nullable', 'string', 'max:40'],
            'category_slug' => ['nullable', 'string'],
            'exam_slugs' => ['nullable', 'array'],
            'exam_slugs.*' => ['string'],
            'date' => ['required', 'date'],
            'referrer' => ['nullable', 'string', 'max:120'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['required', 'string', 'max:40'],
            'payment_timing' => ['required', 'in:before,after'],
        ]);

        $examItems = $this->validatedExamItems($data, $request);
        $category = $this->category($examItems[0]['category_slug']);
        abort_if($category === null, 404);

        $subtotal = round(collect($examItems)->sum('price'), 2);
        $total = max(0, round($subtotal - (float) ($data['discount'] ?? 0), 2));
        $paid = round((float) ($data['paid_amount'] ?? 0), 2);

        if ((float) ($data['discount'] ?? 0) > $subtotal) {
            return back()
                ->withErrors(['discount' => 'El descuento no puede ser mayor al subtotal de examenes.'])
                ->withInput();
        }

        if ($paid > $total) {
            return back()
                ->withErrors(['paid_amount' => 'El pago inicial no puede ser mayor al total neto.'])
                ->withInput();
        }

        $data['price'] = $subtotal;
        $data['exam_items'] = $examItems;
        $data['category_slug'] = $examItems[0]['category_slug'];

        [$order, $movement] = $this->runAtomic(fn () => $this->createOrderWithInitialPayment($data, $category));

        $this->audit->record('order_created', 'order', $order['id'], [
            'patient' => $order['patient_name'],
            'total' => $order['total'],
            'paid' => $order['paid_amount'],
        ]);

        if ($movement) {
            $this->audit->record('cash_income_from_order', 'cash', $movement['id'], ['order_id' => $order['id']]);
        }

        return redirect()->route('orders.show', $order['id'])->with('status', 'Orden creada correctamente.');
    }

    public function show(string $id)
    {
        $order = $this->orders->find($id);
        abort_if($order === null, 404);

        return view('orders.show', [
            'order' => $order,
            'examItems' => $this->orders->examItems($order),
            'orderTitle' => $this->orders->orderTitle($order),
            'whatsappUrl' => $this->canShareResults($order) ? $this->whatsappUrl($order) : null,
        ]);
    }

    public function results(Request $request, string $id)
    {
        $order = $this->orders->find($id);
        abort_if($order === null, 404);
        $examItems = $this->orders->examItems($order);
        $selectedExamIndex = min(max(0, (int) $request->input('exam', 0)), max(0, count($examItems) - 1));

        return view('orders.results', [
            'order' => $order,
            'examItems' => $examItems,
            'selectedExam' => $examItems[$selectedExamIndex],
            'selectedExamIndex' => $selectedExamIndex,
        ]);
    }

    public function saveResults(Request $request, string $id)
    {
        $order = $this->orders->find($id);
        abort_if($order === null, 404);

        $data = $request->validate([
            'tests' => ['array'],
            'tests.*.name' => ['nullable', 'string', 'max:120'],
            'tests.*.unit' => ['nullable', 'string', 'max:60'],
            'tests.*.reference' => ['nullable', 'string', 'max:120'],
            'results' => ['array'],
            'results.*' => ['nullable', 'string', 'max:120'],
            'exam_index' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'in:pending_results,ready'],
        ]);

        $updated = $this->orders->updateResults($id, $data);
        $this->audit->record('order_results_saved', 'order', $id, ['status' => $data['status']]);

        if ($updated && $this->orders->allExamItemsReady($updated)) {
            $record = $this->results->saveFromOrder($updated);
            $this->audit->record('result_archived_from_order', 'result', $record['id'], ['order_id' => $id]);
        }

        return redirect()->route('orders.show', $id)->with('status', 'Resultados guardados.');
    }

    public function pay(Request $request, string $id)
    {
        $order = $this->orders->find($id);
        abort_if($order === null, 404);

        if (($order['status'] ?? null) === 'cancelled') {
            return back()->withErrors(['amount' => 'No se puede cobrar una orden anulada.']);
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'string', 'max:40'],
        ]);

        $balance = max(0, round((float) $order['total'] - (float) $order['paid_amount'], 2));
        $amount = round((float) $data['amount'], 2);

        if ($balance <= 0) {
            return back()->withErrors(['amount' => 'Esta orden ya esta pagada.']);
        }

        if ($amount !== $balance) {
            return back()->withErrors(['amount' => 'El cobro debe ser exactamente el saldo pendiente de Q '.number_format($balance, 2).'.']);
        }

        [$updated, $movement] = $this->runAtomic(function () use ($id, $amount, $data, $order) {
            $updated = $this->orders->addPayment($id, $amount, $data['method']);
            abort_if($updated === null, 404);

            $movement = $this->cash->create([
                'type' => 'income',
                'date' => now()->toDateString(),
                'amount' => $amount,
                'method' => $data['method'],
                'description' => 'Abono de orden '.$id.' - '.$order['patient_name'],
                'order_id' => $id,
                'source' => 'order_payment',
            ]);

            return [$updated, $movement];
        });
        $this->audit->record('order_payment_added', 'order', $id, ['amount' => $amount, 'movement_id' => $movement['id']]);

        return redirect()->route('orders.show', $id)->with('status', 'Pago registrado. Estado: '.$this->paymentStatusLabel($updated['payment_status']));
    }

    public function deliver(string $id)
    {
        $order = $this->orders->find($id);
        abort_if($order === null, 404);

        $balance = max(0, round((float) $order['total'] - (float) $order['paid_amount'], 2));

        if (($order['payment_status'] ?? null) !== 'paid' || $balance > 0) {
            return back()->withErrors(['delivery' => 'No se puede entregar: saldo pendiente Q '.number_format($balance, 2).'.']);
        }

        if (($order['status'] ?? null) === 'delivered') {
            return back()->withErrors(['delivery' => 'Esta orden ya fue entregada.']);
        }

        if (! $this->orders->allExamItemsReady($order)) {
            return back()->withErrors(['delivery' => 'No puede entregarse: existen examenes pendientes.']);
        }

        $updated = $this->orders->markDelivered($id);
        $this->audit->record('order_delivered', 'order', $id);

        if ($updated && $this->orderHasAnyResult($updated)) {
            $record = $this->results->saveFromOrder($updated);
            $this->audit->record('result_archived_on_delivery', 'result', $record['id'], ['order_id' => $id]);
        }

        return redirect()->route('orders.show', $id)->with('status', 'Orden marcada como entregada.');
    }

    public function cancel(Request $request, string $id)
    {
        $order = $this->orders->find($id);
        abort_if($order === null, 404);

        if (($order['status'] ?? null) === 'cancelled') {
            return back()->withErrors(['reason' => 'Esta orden ya fue anulada.']);
        }

        $data = $request->validate(['reason' => ['required', 'string', 'max:240']]);

        [$updated, $movement] = $this->runAtomic(function () use ($id, $data, $order) {
            $updated = $this->orders->cancel($id, $data['reason']);
            abort_if($updated === null, 404);

            $movement = null;

            if ((float) ($updated['paid_amount'] ?? 0) > 0 && ! $this->cash->hasOrderReversal($id)) {
                $movement = $this->cash->create([
                    'type' => 'expense',
                    'date' => now()->toDateString(),
                    'amount' => (float) $updated['paid_amount'],
                    'method' => $updated['payment_method'] ?? 'efectivo',
                    'description' => 'Reverso por anulacion de orden '.$id.' - '.$order['patient_name'],
                    'order_id' => $id,
                    'source' => 'order_cancel_reversal',
                ]);
            }

            return [$updated, $movement];
        });

        if ($movement) {
            $this->audit->record('cash_reversal_from_order_cancel', 'cash', $movement['id'], ['order_id' => $id]);
        }

        $this->audit->record('order_cancelled', 'order', $id, [
            'reason' => $data['reason'],
            'paid_amount' => $order['paid_amount'] ?? 0,
            'status' => $updated['status'] ?? 'cancelled',
        ]);

        return redirect()->route('orders.show', $id)->with('status', 'Orden anulada.');
    }

    public function pdf(string $id)
    {
        $order = $this->orders->find($id);
        abort_if($order === null, 404);
        $this->ensurePrintableOrder($order);

        return $this->pdfResponse($order, 'attachment');
    }

    public function pdfExam(string $id, int $exam)
    {
        $order = $this->orders->find($id);
        abort_if($order === null, 404);
        $this->ensurePrintableExam($order, $exam);

        return $this->pdfResponse($order, 'attachment', $exam);
    }

    public function print(string $id)
    {
        $order = $this->orders->find($id);
        abort_if($order === null, 404);
        $this->ensurePrintableOrder($order);

        return $this->pdfResponse($order, 'inline');
    }

    private function category(string $slug): ?array
    {
        return Arr::first($this->catalog->categories(), fn (array $category) => $category['slug'] === $slug);
    }

    private function validatedExamItems(array $data, Request $request): array
    {
        $slugs = collect($data['exam_slugs'] ?? [])
            ->filter()
            ->values();

        if ($slugs->isEmpty() && filled($data['category_slug'] ?? null)) {
            $slugs = collect([$data['category_slug']]);
        }

        if ($slugs->isEmpty()) {
            abort(422, 'Debe seleccionar al menos un examen.');
        }

        if ($slugs->duplicates()->isNotEmpty()) {
            abort(422, 'No se permite seleccionar examenes duplicados.');
        }

        $prices = $this->catalog->prices();
        $postedPrices = collect($request->input('exam_prices', []));

        return $slugs
            ->map(function (string $slug) use ($prices, $postedPrices, $data) {
                $category = $this->category($slug);
                abort_if($category === null, 422, 'El examen seleccionado no existe o no esta activo.');

                $catalogPrice = round((float) ($prices[$slug] ?? 0), 2);
                $postedPrice = round((float) ($postedPrices[$slug] ?? ($data['price'] ?? 0)), 2);
                $price = $catalogPrice > 0 ? $catalogPrice : $postedPrice;

                if ($catalogPrice > 0 && abs($postedPrice - $catalogPrice) > 0.001) {
                    abort(422, 'El precio del examen fue manipulado. Vuelva a seleccionar el examen.');
                }

                if ($price < 0) {
                    abort(422, 'El precio del examen no puede ser negativo.');
                }

                return [
                    'category_slug' => $category['slug'],
                    'category_name' => $category['name'],
                    'category_title' => $category['title'],
                    'price' => $price,
                    'tests' => $category['tests'] ?? [],
                ];
            })
            ->values()
            ->all();
    }

    private function createOrderWithInitialPayment(array $data, array $category): array
    {
        $order = $this->orders->create($data, $category);
        $movement = null;

        if ((float) ($data['paid_amount'] ?? 0) > 0) {
            $movement = $this->cash->create([
                'type' => 'income',
                'date' => $data['date'],
                'amount' => (float) $data['paid_amount'],
                'method' => $data['payment_method'],
                'description' => 'Pago de orden '.$order['id'].' - '.$order['patient_name'],
                'order_id' => $order['id'],
                'source' => 'order_payment',
            ]);
        }

        return [$order, $movement];
    }

    private function runAtomic(callable $callback): mixed
    {
        if ($this->orders->usesDatabaseStorage()) {
            return DB::transaction($callback);
        }

        return $callback();
    }

    private function whatsappUrl(array $order): string
    {
        $phone = preg_replace('/\D+/', '', $order['phone'] ?? '');
        $message = 'Hola '.$order['patient_name'].', sus resultados de Laboratorio BIOLAB estan listos. Puede pasar por ellos o solicitar el PDF.';

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($message);
    }

    private function canShareResults(array $order): bool
    {
        return in_array($order['status'] ?? null, ['ready', 'delivered'], true)
            && ($order['payment_status'] ?? null) === 'paid'
            && $this->orders->allExamItemsReady($order);
    }

    private function ensurePrintableOrder(array $order): void
    {
        if (($order['status'] ?? null) === 'cancelled') {
            abort(403, 'No se puede imprimir una orden anulada.');
        }

        if (! $this->canShareResults($order)) {
            abort(403, 'No se puede imprimir: la orden debe estar pagada y con resultados listos.');
        }
    }

    private function ensurePrintableExam(array $order, int $examIndex): void
    {
        if (($order['status'] ?? null) === 'cancelled') {
            abort(403, 'No se puede imprimir una orden anulada.');
        }

        if (($order['payment_status'] ?? null) !== 'paid') {
            abort(403, 'No se puede imprimir: la orden debe estar pagada.');
        }

        $examItem = $this->orders->examItems($order)[$examIndex] ?? null;
        abort_if($examItem === null, 404);

        if (($examItem['status'] ?? null) !== 'ready') {
            abort(403, 'No se puede imprimir: el examen seleccionado aun esta pendiente.');
        }
    }

    private function reportPayload(array $order): array
    {
        return [
            'order' => $order,
            'examItems' => $this->orders->examItems($order),
            'business' => config('lab.business'),
            'logoDataUri' => $this->assetDataUri(public_path('assets/biolab-logo-pdf.jpg'), 'image/jpeg'),
            'signatureDataUri' => $this->assetDataUri(public_path('assets/firma-biolab-pdf.jpg'), 'image/jpeg'),
        ];
    }

    private function orderHasAnyResult(array $order): bool
    {
        return collect($this->orders->examItems($order))
            ->contains(fn (array $item) => collect($item['results'] ?? [])->contains(fn ($value) => filled($value)));
    }

    private function paymentStatusLabel(string $status): string
    {
        return [
            'unpaid' => 'Sin pago',
            'partial' => 'Parcial',
            'paid' => 'Pagado',
        ][$status] ?? $status;
    }

    private function pdfResponse(array $order, string $disposition, ?int $examIndex = null)
    {
        $payload = $this->reportPayload($order);
        if ($examIndex !== null) {
            $payload['examItems'] = [($payload['examItems'][$examIndex] ?? abort(404))];
        }

        $html = view('orders.pdf', $payload)->render();
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->setPaper('letter');
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->render();

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="orden-'.$order['id'].'-'.Str::slug($order['patient_name']).'.pdf"',
        ]);
    }

    private function assetDataUri(string $path, string $mime): string
    {
        return file_exists($path)
            ? 'data:'.$mime.';base64,'.base64_encode(file_get_contents($path))
            : '';
    }
}

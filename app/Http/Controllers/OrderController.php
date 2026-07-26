<?php

namespace App\Http\Controllers;

use App\Services\CashStore;
use App\Services\CatalogStore;
use App\Services\AuditStore;
use App\Services\OrderStore;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderStore $orders,
        private readonly CatalogStore $catalog,
        private readonly CashStore $cash,
        private readonly AuditStore $audit,
    ) {
    }

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
            'category_slug' => ['required', 'string'],
            'date' => ['required', 'date'],
            'referrer' => ['nullable', 'string', 'max:120'],
            'price' => ['required', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['required', 'string', 'max:40'],
            'payment_timing' => ['required', 'in:before,after'],
        ]);

        $category = $this->category($data['category_slug']);
        abort_if($category === null, 404);

        $total = max(0, round((float) ($data['price'] ?? 0) - (float) ($data['discount'] ?? 0), 2));
        $paid = round((float) ($data['paid_amount'] ?? 0), 2);

        if ($paid > $total) {
            return back()
                ->withErrors(['paid_amount' => 'El pago inicial no puede ser mayor al total neto.'])
                ->withInput();
        }

        $order = $this->orders->create($data, $category);
        $this->audit->record('order_created', 'order', $order['id'], [
            'patient' => $order['patient_name'],
            'total' => $order['total'],
            'paid' => $order['paid_amount'],
        ]);

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
            'whatsappUrl' => $this->whatsappUrl($order),
        ]);
    }

    public function results(string $id)
    {
        $order = $this->orders->find($id);
        abort_if($order === null, 404);

        return view('orders.results', ['order' => $order]);
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
            'status' => ['required', 'in:pending_results,ready'],
        ]);

        $this->orders->updateResults($id, $data);
        $this->audit->record('order_results_saved', 'order', $id, ['status' => $data['status']]);

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

        $updated = $this->orders->addPayment($id, $amount, $data['method']);

        $movement = $this->cash->create([
            'type' => 'income',
            'date' => now()->toDateString(),
            'amount' => $amount,
            'method' => $data['method'],
            'description' => 'Abono de orden '.$id.' - '.$order['patient_name'],
            'order_id' => $id,
            'source' => 'order_payment',
        ]);
        $this->audit->record('order_payment_added', 'order', $id, ['amount' => $amount, 'movement_id' => $movement['id']]);

        return redirect()->route('orders.show', $id)->with('status', 'Pago registrado. Estado: '.$updated['payment_status']);
    }

    public function deliver(string $id)
    {
        $this->orders->markDelivered($id);
        $this->audit->record('order_delivered', 'order', $id);

        return redirect()->route('orders.show', $id)->with('status', 'Orden marcada como entregada.');
    }

    public function cancel(Request $request, string $id)
    {
        $order = $this->orders->find($id);
        abort_if($order === null, 404);

        $data = $request->validate(['reason' => ['required', 'string', 'max:240']]);
        $updated = $this->orders->cancel($id, $data['reason']);

        if ((float) ($order['paid_amount'] ?? 0) > 0) {
            $movement = $this->cash->create([
                'type' => 'expense',
                'date' => now()->toDateString(),
                'amount' => (float) $order['paid_amount'],
                'method' => $order['payment_method'] ?? 'efectivo',
                'description' => 'Reverso por anulacion de orden '.$id.' - '.$order['patient_name'],
                'order_id' => $id,
                'source' => 'order_cancel_reversal',
            ]);
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

        $html = view('orders.pdf', [
            'order' => $order,
            'business' => config('lab.business'),
            'logoDataUri' => $this->assetDataUri(public_path('assets/biolab-logo-pdf.jpg'), 'image/jpeg'),
            'signatureDataUri' => $this->assetDataUri(public_path('assets/firma-biolab-pdf.jpg'), 'image/jpeg'),
        ])->render();
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->setPaper('letter');
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->render();

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="orden-'.$id.'-'.Str::slug($order['patient_name']).'.pdf"',
        ]);
    }

    private function category(string $slug): ?array
    {
        return Arr::first($this->catalog->categories(), fn (array $category) => $category['slug'] === $slug);
    }

    private function whatsappUrl(array $order): string
    {
        $phone = preg_replace('/\D+/', '', $order['phone'] ?? '');
        $message = 'Hola '.$order['patient_name'].', sus resultados de Laboratorio BIOLAB estan listos. Puede pasar por ellos o solicitar el PDF.';

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($message);
    }

    private function assetDataUri(string $path, string $mime): string
    {
        return file_exists($path)
            ? 'data:'.$mime.';base64,'.base64_encode(file_get_contents($path))
            : '';
    }
}

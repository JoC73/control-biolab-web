<?php

namespace App\Http\Controllers;

use App\Services\CatalogStore;
use App\Services\AuditStore;
use App\Services\LabResultStore;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class LabController extends Controller
{
    public function __construct(
        private readonly LabResultStore $results,
        private readonly CatalogStore $catalog,
        private readonly AuditStore $audit,
    )
    {
    }

    public function index()
    {
        return view('lab.index', [
            'business' => config('lab.business'),
            'categories' => $this->catalog->categories(),
            'recentResults' => $this->results->all()->take(5),
        ]);
    }

    public function create(string $category, ?array $savedResult = null)
    {
        $categoryConfig = $this->findCategory($category);

        abort_if($categoryConfig === null, 404);

        return view('lab.create', [
            'business' => config('lab.business'),
            'category' => $categoryConfig,
            'referrers' => $this->catalog->referrers(),
            'savedResult' => $savedResult,
        ]);
    }

    public function preview(Request $request, string $category)
    {
        $payload = $this->resultPayload($request, $category);

        return view('lab.preview', $payload);
    }

    public function save(Request $request, string $category)
    {
        $payload = $this->resultPayload($request, $category);
        $record = $this->results->save($payload);
        $this->audit->record('result_saved', 'result', $record['id'], [
            'patient' => $record['patient_name'],
            'category' => $record['category_name'],
        ]);

        return redirect()
            ->route('lab.results.show', $record['id'])
            ->with('status', 'Resultado guardado correctamente.');
    }

    public function history(Request $request)
    {
        return view('lab.history', [
            'business' => config('lab.business'),
            'results' => $this->results->search($request->only('q', 'date_from', 'date_to')),
            'filters' => $request->only('q', 'date_from', 'date_to'),
        ]);
    }

    public function show(string $id)
    {
        $record = $this->results->find($id);

        abort_if($record === null, 404);

        return view('lab.preview', $this->payloadFromRecord($record) + [
            'savedResult' => $record,
        ]);
    }

    public function edit(string $id)
    {
        $record = $this->results->find($id);

        abort_if($record === null, 404);

        return $this->create($record['category_slug'], $record);
    }

    public function update(Request $request, string $id)
    {
        $record = $this->results->find($id);

        abort_if($record === null, 404);

        $payload = $this->resultPayload($request, $record['category_slug']);
        $updated = $this->results->update($id, $payload);

        abort_if($updated === null, 404);
        $this->audit->record('result_updated', 'result', $id, [
            'patient' => $updated['patient_name'],
            'category' => $updated['category_name'],
        ]);

        return redirect()
            ->route('lab.results.show', $id)
            ->with('status', 'Resultado actualizado correctamente.');
    }

    public function destroy(string $id)
    {
        $this->results->delete($id);
        $this->audit->record('result_deleted', 'result', $id);

        return redirect()
            ->route('lab.history')
            ->with('status', 'Resultado eliminado.');
    }

    public function pdf(Request $request, string $category)
    {
        $payload = $this->resultPayload($request, $category);

        return $this->downloadPdf($payload);
    }

    public function savedPdf(string $id)
    {
        $record = $this->results->find($id);

        abort_if($record === null, 404);

        return $this->downloadPdf($this->payloadFromRecord($record));
    }

    private function downloadPdf(array $payload)
    {
        $payload['logoDataUri'] = $this->assetDataUri(public_path('assets/biolab-logo-pdf.jpg'), 'image/jpeg');
        $payload['signatureDataUri'] = $this->assetDataUri(public_path('assets/firma-biolab-pdf.jpg'), 'image/jpeg');
        $html = view('lab.pdf', $payload)->render();

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $pdf = new Dompdf($options);
        $pdf->setPaper('letter');
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->render();

        $patient = Str::slug($payload['result']['patient_name'] ?: 'paciente');
        $filename = sprintf('resultado-%s-%s.pdf', $payload['category']['slug'], $patient);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function findCategory(string $category): ?array
    {
        return Arr::first($this->catalog->categories(), fn (array $item) => $item['slug'] === $category);
    }

    private function resultPayload(Request $request, string $category): array
    {
        $categoryConfig = $this->findCategory($category);

        abort_if($categoryConfig === null, 404);

        $data = $request->validate([
            'patient_name' => ['required', 'string', 'max:160'],
            'age' => ['nullable', 'string', 'max:40'],
            'referred_by' => ['nullable', 'string', 'max:120'],
            'date' => ['required', 'date'],
            'tests' => ['array'],
            'tests.*.name' => ['nullable', 'string', 'max:120'],
            'tests.*.unit' => ['nullable', 'string', 'max:60'],
            'tests.*.reference' => ['nullable', 'string', 'max:120'],
            'results' => ['array'],
            'results.*' => ['nullable', 'string', 'max:120'],
        ]);

        $tests = collect($data['tests'] ?? [])
            ->filter(function (array $test, int $index) use ($data) {
                return filled($test['name'] ?? null)
                    || filled($test['unit'] ?? null)
                    || filled($test['reference'] ?? null)
                    || filled($data['results'][$index] ?? null);
            })
            ->map(fn (array $test) => [
                'name' => $test['name'] ?? '',
                'unit' => $test['unit'] ?? '',
                'reference' => $test['reference'] ?? '',
            ])
            ->values()
            ->all();

        return [
            'business' => config('lab.business'),
            'category' => $categoryConfig,
            'result' => $data,
            'tests' => $tests,
        ];
    }

    private function payloadFromRecord(array $record): array
    {
        return [
            'business' => config('lab.business'),
            'category' => [
                'slug' => $record['category_slug'],
                'name' => $record['category_name'],
                'title' => $record['category_title'],
                'tests' => $record['tests'],
            ],
            'result' => [
                'patient_name' => $record['patient_name'],
                'age' => $record['age'] ?? '',
                'referred_by' => $record['referred_by'] ?? '',
                'date' => $record['date'],
                'results' => $record['results'] ?? [],
            ],
            'tests' => $record['tests'] ?? [],
        ];
    }

    private function assetDataUri(string $path, string $mime): string
    {
        return file_exists($path)
            ? 'data:'.$mime.';base64,'.base64_encode(file_get_contents($path))
            : '';
    }
}

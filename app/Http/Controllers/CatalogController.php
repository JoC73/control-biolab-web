<?php

namespace App\Http\Controllers;

use App\Services\CatalogStore;
use App\Services\AuditStore;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function __construct(
        private readonly CatalogStore $catalog,
        private readonly AuditStore $audit,
    )
    {
    }

    public function index()
    {
        return view('catalog.index', [
            'categories' => $this->catalog->categories(),
            'referrers' => $this->catalog->referrers(),
            'prices' => $this->catalog->prices(),
        ]);
    }

    public function referrer(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $this->catalog->addReferrer($data['name']);
        $this->audit->record('catalog_referrer_created', 'catalog', null, ['name' => $data['name']]);

        return redirect()->route('catalog.index')->with('status', 'Referencia medica agregada.');
    }

    public function price(Request $request)
    {
        $data = $request->validate([
            'slug' => ['required', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
        ]);

        $this->catalog->savePrice($data['slug'], (float) $data['price']);
        $this->audit->record('catalog_price_updated', 'catalog', $data['slug'], ['price' => $data['price']]);

        return redirect()->route('catalog.index')->with('status', 'Precio actualizado.');
    }

    public function exam(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'title' => ['nullable', 'string', 'max:140'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'tests' => ['array'],
            'tests.*.name' => ['nullable', 'string', 'max:120'],
            'tests.*.unit' => ['nullable', 'string', 'max:60'],
            'tests.*.reference' => ['nullable', 'string', 'max:120'],
        ]);

        $exam = $this->catalog->saveExam($data);
        $this->audit->record('catalog_exam_created', 'catalog', $exam['slug'], ['name' => $exam['name']]);

        return redirect()->route('lab.index')->with('status', 'Examen personalizado creado.');
    }

    public function deleteExam(string $slug)
    {
        $this->catalog->deleteExam($slug);
        $this->audit->record('catalog_exam_deleted', 'catalog', $slug);

        return redirect()->route('catalog.index')->with('status', 'Examen personalizado eliminado.');
    }
}

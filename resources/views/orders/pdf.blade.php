@include('lab.pdf', [
    'business' => $business,
    'category' => ['title' => count($examItems) === 1 ? $examItems[0]['category_title'] : 'Resultados consolidados', 'slug' => $order['category_slug']],
    'result' => ['patient_name' => $order['patient_name'], 'age' => $order['age'], 'referred_by' => $order['referrer'], 'date' => $order['date']],
    'tests' => count($examItems) === 1 ? ($examItems[0]['tests'] ?? []) : [],
    'examItems' => $examItems,
    'groupedPdf' => count($examItems) > 1,
])

@include('lab.pdf', [
    'business' => $business,
    'category' => ['title' => $order['category_title'], 'slug' => $order['category_slug']],
    'result' => ['patient_name' => $order['patient_name'], 'age' => $order['age'], 'referred_by' => $order['referrer'], 'date' => $order['date'], 'results' => $order['results']],
    'tests' => $order['tests'],
])

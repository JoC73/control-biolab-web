<?php

namespace App\Http\Controllers;

use App\Services\AuditStore;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AuditController extends Controller
{
    public function __construct(private readonly AuditStore $audit) {}

    public function index(Request $request)
    {
        $filters = $request->only('q', 'action');
        $records = $this->audit->all()
            ->sortByDesc('created_at')
            ->when($filters['q'] ?? null, function (Collection $records, string $query) {
                $query = Str::lower($query);

                return $records->filter(fn (array $record) => Str::contains(Str::lower($record['user_name'] ?? ''), $query)
                    || Str::contains(Str::lower($record['subject_type'] ?? ''), $query)
                    || Str::contains(Str::lower($record['subject_id'] ?? ''), $query));
            })
            ->when($filters['action'] ?? null, fn (Collection $records, string $action) => $records->where('action', $action))
            ->take(200)
            ->values();

        return view('audit.index', [
            'records' => $records,
            'filters' => $filters,
            'actions' => $this->audit->all()->pluck('action')->unique()->sort()->values(),
        ]);
    }
}

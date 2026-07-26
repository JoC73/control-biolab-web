<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

abstract class JsonStore
{
    public function all(): Collection
    {
        return collect($this->read());
    }

    protected function read(): array
    {
        if (! File::exists($this->path())) {
            return [];
        }

        $records = json_decode(File::get($this->path()), true);

        return is_array($records) ? $records : [];
    }

    protected function write(array $records): void
    {
        File::ensureDirectoryExists(dirname($this->path()));
        File::put($this->path(), json_encode(array_values($records), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    abstract protected function path(): string;
}

<?php

declare(strict_types=1);

namespace Juling\DevTools\Support;

use Illuminate\Support\Str;

class DevConfig
{
    private array $config;

    public function __construct()
    {
        $this->config = config('devtools');
    }

    public function getDist(string $moduleName = ''): string
    {
        $dist = Str::rtrim($this->config['dist'], '/');
        if (! empty($moduleName)) {
            $dist .= '/'.$moduleName;
        }

        return $dist;
    }

    public function getMultiModule(): bool
    {
        return $this->config['multi_module'];
    }

    public function getIgnoreTables(): array
    {
        return $this->config['exclude_tables'];
    }

    public function getIgnoreColumns(string $tableName = ''): array
    {
        $ignoreColumns = $this->config['exclude_columns'];

        $columns = array_filter($ignoreColumns, function ($v, $k) {
            return is_int($k);
        }, ARRAY_FILTER_USE_BOTH);

        if (! empty($tableName) && isset($ignoreColumns[$tableName])) {
            $columns = array_merge($columns, $ignoreColumns[$tableName]);
        }

        return $columns;
    }

    public function getIgnoreControllers(): array
    {
        return $this->config['exclude_controllers'];
    }

    public function getIgnoreSingular(): bool
    {
        return $this->config['ignore_singular'];
    }
}

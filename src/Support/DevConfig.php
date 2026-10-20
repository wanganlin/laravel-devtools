<?php

declare(strict_types=1);

namespace Juling\DevTools\Support;

use Illuminate\Support\Str;

class DevConfig
{
    private array $config;

    public function __construct()
    {
        $this->config = config('devtools', []);
    }

    public function getDist(string $moduleName = ''): string
    {
        $dist = Str::rtrim($this->config['dist'] ?? base_path(), '/');
        if (! empty($moduleName)) {
            $dist .= '/'.$moduleName;
        }

        return $dist;
    }

    /**
     * 是否启用领域模块模式（app/Domains/{Group}/... 分层结构）
     */
    public function getDomainMode(): bool
    {
        return (bool) ($this->config['domain_mode'] ?? false);
    }

    public function getIgnoreTables(): array
    {
        return $this->config['exclude_tables'] ?? [];
    }

    public function getIgnoreColumns(string $tableName = ''): array
    {
        $ignoreColumns = $this->config['exclude_columns'] ?? [];

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
        return $this->config['exclude_controllers'] ?? [];
    }
}

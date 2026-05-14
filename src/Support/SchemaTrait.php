<?php

declare(strict_types=1);

namespace Juling\DevTools\Support;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

trait SchemaTrait
{
    private array $ignoreTables = [];

    private bool $ignoreSingular = true;

    private function getTables(?string $tablePrefix = null, ?string $tableName = null): array
    {
        $this->ignoreTables = array_merge($this->ignoreTables, config('devtools.exclude_tables', []));

        $tables = Schema::getTables();
        foreach ($tables as $key => $table) {
            if (in_array($table['name'], $this->ignoreTables) || $table['schema'] !== config('database.connections.mysql.database')) {
                unset($tables[$key]);
            }

            // 匹配表前缀
            if (! empty($tablePrefix)) {
                if (! Str::startsWith($table['name'], $tablePrefix)) {
                    unset($tables[$key]);
                }
            }

            // 匹配表名称
            if (! empty($tableName)) {
                if ($table['name'] !== $tableName) {
                    unset($tables[$key]);
                }
            }
        }

        return $tables;
    }

    private function getTableIndexes(string $tableName): array
    {
        $indexes = Schema::getIndexes($tableName);

        $columns = [];
        foreach ($indexes as $key => $item) {
            foreach ($item['columns'] as $column) {
                $columns[$key] = [
                    'name' => $column,
                    'camel_name' => Str::camel($column),
                    'studly_name' => Str::studly($column),
                    'unique' => $item['unique'],
                    'primary' => $item['primary'],
                ];
            }
        }

        return $columns;
    }

    private function getTableColumns(string $tableName, ?callable $callback = null): array
    {
        $columns = Schema::getColumns($tableName);
        $indexes = Schema::getIndexes($tableName);
        $indexes = Arr::pluck($indexes, 'columns');
        $indexes = Arr::collapse($indexes);

        foreach ($columns as $key => $column) {
            $comment = Str::replace([':', '：', ' ', '(', '（'], ':', $column['comment']);
            $comments = explode(':', $comment);
            $column['comment_short'] = Arr::first($comments);
            if (empty($column['comment'])) {
                if ($column['name'] === 'id') {
                    $column['comment'] = 'ID';
                } elseif ($column['name'] === 'created_at') {
                    $column['comment'] = '创建时间';
                } elseif ($column['name'] === 'updated_at') {
                    $column['comment'] = '更新时间';
                } elseif ($column['name'] === 'deleted_at') {
                    $column['comment'] = '删除时间';
                } else {
                    $column['comment'] = '';
                }
            }

            $column['index'] = in_array($column['name'], $indexes);
            $column['camel_name'] = Str::camel($column['name']);
            $column['studly_name'] = Str::studly($column['name']);
            $column['base_type'] = $callback($column['type_name']);
            $column['swagger_type'] = $column['base_type'] === 'int' ? 'integer' : $column['base_type'];
            $columns[$key] = $column;
        }

        return $columns;
    }

    private function getTablePrimaryKey(string $tableName): string
    {
        $columns = Schema::getIndexes($tableName);

        $primaryKey = 'id';
        foreach ($columns as $column) {
            if ($column['primary']) {
                $primaryKey = Arr::first($column['columns']);
                break;
            }
        }

        return $primaryKey;
    }

    private function getTableGroupName(string $tableName): string
    {
        $groups = explode('_', $tableName);

        return Str::studly($this->getSingular($groups[0]));
    }

    private function resolve(string $genType, array $table = []): void
    {
        $namespace = str_replace('\\', '/', __NAMESPACE__);
        $namespace = str_replace('/', '\\', dirname($namespace));

        if (! empty($table)) {
            $singular = $this->getSingular($table['name']);
            $table = [
                'tableName' => $table['name'],
                'className' => Str::studly($singular),
                'camelName' => Str::camel($singular),
                'snakeName' => Str::snake($singular),
                'tableComment' => StrHelper::rtrim($table['comment'], '表'),
            ];
        }

        $resolver = '\\'.$namespace.'\\Resolvers\\'.Str::studly($genType).'Resolver';
        if (method_exists($resolver, 'build')) {
            $devConfig = new DevConfig;
            (new $resolver)->build($devConfig, $table);
        }
    }

    private function getSingular(string $name): string
    {
        $this->ignoreSingular = config('devtools.ignore_singular', $this->ignoreSingular);

        if ($this->ignoreSingular) {
            return $name;
        }

        return Str::singular($name);
    }

    private function ensureDirectoryExists(array|string $dirs): void
    {
        $fs = new Filesystem;

        if (is_string($dirs)) {
            $dirs = [$dirs];
        }

        foreach ($dirs as $dir) {
            $fs->ensureDirectoryExists($dir);
        }
    }

    private function deleteDirectories(string $directory): void
    {
        $fs = new Filesystem;

        if ($fs->isDirectory($directory)) {
            $fs->deleteDirectory($directory);
        }
    }
}

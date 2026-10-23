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

    private bool $convertToSingular = true;

    private function getTables(?string $tablePrefix = null, ?string $tableName = null): array
    {
        $this->ignoreTables = array_merge($this->ignoreTables, config('devtools.exclude_tables', []));

        $database = Schema::getConnection()->getDatabaseName();
        $tables = Schema::getTables();

        foreach ($tables as $key => $table) {
            if (in_array($table['name'], $this->ignoreTables) || $table['schema'] !== $database) {
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

            // 批量生成时跳过中间关联表（中间表由 belongsToMany 关联维护，无需生成 entity/model/service 等业务代码）
            if (empty($tableName) && isset($tables[$key]) && $this->isPivotTable($table['name'])) {
                unset($tables[$key]);
            }
        }

        return $tables;
    }

    /**
     * 判断是否为中间关联表（Pivot Table）。
     *
     * 中间表用于维护两张实体表之间的多对多关系（如 role_user 关联 roles、users），
     * 通常由 belongsToMany 的 attach/detach/sync 维护，无需生成独立的业务代码。
     *
     * 判定规则：表没有以 id 为单列主键即视为中间表
     * （业务实体表都拥有 id 自增主键，而中间表多为联合主键或无主键）。
     */
    private function isPivotTable(string $tableName): bool
    {
        foreach (Schema::getIndexes($tableName) as $index) {
            if ($index['primary'] && $index['columns'] === ['id']) {
                return false;
            }
        }

        return true;
    }

    private function getTableIndexes(string $tableName): array
    {
        $indexes = Schema::getIndexes($tableName);

        $columns = [];
        foreach ($indexes as $item) {
            $isSingleColumn = count($item['columns']) === 1;
            // 单列唯一才是字段真正的 unique；复合唯一索引不代表单列独立唯一
            $isUnique = (bool) ($item['unique'] && $isSingleColumn);
            $isPrimary = (bool) $item['primary'];

            foreach ($item['columns'] as $index => $column) {
                if (! isset($columns[$column])) {
                    $columns[$column] = [
                        'name' => $column,
                        'camel_name' => Str::camel($column),
                        'studly_name' => Str::studly($column),
                        'unique' => $isUnique,
                        'primary' => $isPrimary,
                        'is_composite' => ! $isSingleColumn,
                        'is_lead_column' => $index === 0, // 联合索引最左前缀列标识
                        'index_names' => [$item['name']],
                    ];
                } else {
                    // 若已被其他索引录入，合并其索引属性（优先保留最高约束）
                    $columns[$column]['unique'] = $columns[$column]['unique'] || $isUnique;
                    $columns[$column]['primary'] = $columns[$column]['primary'] || $isPrimary;
                    $columns[$column]['index_names'][] = $item['name'];
                }
            }
        }

        return array_values($columns);
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
            // 类型映射回调可选（字典文档等场景不需要 base_type/swagger_type）
            if ($callback !== null) {
                $column['base_type'] = $callback($column['type_name']);
                $column['swagger_type'] = $column['base_type'] === 'int' ? 'integer' : $column['base_type'];
                $isNullable = ! empty($column['nullable']);
                $column['property_type'] = ($isNullable ? '?' : '').$column['base_type'];
            }
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

        return Str::studly($this->convertToSingular($groups[0]));
    }

    private function resolve(string $genType, array $table = []): void
    {
        $namespace = str_replace('\\', '/', __NAMESPACE__);
        $namespace = str_replace('/', '\\', dirname($namespace));

        if (! empty($table)) {
            $singularTableName = $this->convertToSingular($table['name']);
            $table = [
                'tableName' => $table['name'],
                'className' => Str::studly($singularTableName),
                'camelName' => Str::camel($singularTableName),
                'snakeName' => Str::snake($singularTableName),
                'tableComment' => Str::rtrim($table['comment'], '表'),
            ];
        }

        $resolver = '\\'.$namespace.'\\Resolvers\\'.Str::studly($genType).'Resolver';
        if (method_exists($resolver, 'build')) {
            $devConfig = new DevConfig;
            $instance = new $resolver;
            // 命令上下文注入 --force 选项（默认跳过已存在文件）
            if (method_exists($this, 'option')) {
                $instance->force = (bool) $this->option('force');
            }
            $instance->build($devConfig, $table);
        }
    }

    private function convertToSingular(string $name): string
    {
        $this->convertToSingular = config('devtools.convert_to_singular', $this->convertToSingular);

        if ($this->convertToSingular) {
            return Str::singular($name);
        }

        return $name;
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

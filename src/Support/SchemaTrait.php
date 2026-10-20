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

        // 快照当前库的全部表名，供 isPivotTable 复用（避免逐表重复查询全表清单）
        $existingTables = array_flip(array_column(array_filter($tables, fn ($table) => $table['schema'] === $database), 'name'));

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
            if (empty($tableName) && isset($tables[$key]) && $this->isPivotTable($table['name'], $existingTables)) {
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
     * 判定规则（满足其一）：
     * 1. 恰好包含两个外键，且除主键、外键列、时间戳列外没有其他业务字段；
     * 2. 表名可按 Laravel 约定拆分为两张已存在的关联表（如 role_user 拆分为 role、user），
     *    且除主键、对应的两个外键列、时间戳列外没有其他业务字段（适用于未定义外键约束的表）；
     * 3. 联合主键，且除主键、时间戳列外全部为 *_id 列或成对的多态 *_type/*_id 列
     *    （结构特征不依赖命名与外键约束，覆盖 taggables 多态中间表、admin_role_permissions 三向关联表）。
     */
    private function isPivotTable(string $tableName, ?array $existingTables = null): bool
    {
        $allowColumns = ['id', 'created_at', 'updated_at', 'deleted_at'];
        $columns = array_column(Schema::getColumns($tableName), 'name');

        $foreignKeys = Schema::getForeignKeys($tableName);
        if (count($foreignKeys) === 2) {
            $fkColumns = Arr::collapse(Arr::pluck($foreignKeys, 'columns'));

            if (empty(array_diff($columns, array_merge($allowColumns, $fkColumns)))) {
                return true;
            }
        }

        if ($existingTables === null) {
            $database = Schema::getConnection()->getDatabaseName();
            $existingTables = array_flip(array_column(array_filter(Schema::getTables(), fn ($table) => $table['schema'] === $database), 'name'));
        }

        $parts = explode('_', $tableName);
        for ($i = 1; $i < count($parts); $i++) {
            $left = Str::singular(implode('_', array_slice($parts, 0, $i)));
            $right = Str::singular(implode('_', array_slice($parts, $i)));
            $fkColumns = [$left.'_id', $right.'_id'];

            if (! in_array($fkColumns[0], $columns) || ! in_array($fkColumns[1], $columns)) {
                continue;
            }
            if (! empty(array_diff($columns, array_merge($allowColumns, $fkColumns)))) {
                continue;
            }

            if (isset($existingTables[Str::plural($left)], $existingTables[Str::plural($right)])) {
                return true;
            }
        }

        // 规则三：联合主键，且除主键、时间戳列外全部为 *_id 列或成对的多态 *_type/*_id 列
        $primaryColumns = [];
        foreach (Schema::getIndexes($tableName) as $index) {
            if ($index['primary']) {
                $primaryColumns = $index['columns'];
                break;
            }
        }

        if (count($primaryColumns) >= 2) {
            foreach ($columns as $column) {
                if (in_array($column, $allowColumns, true) || preg_match('/_id$/', $column)) {
                    continue;
                }
                // 多态约定：{name}_type 必须与同名 {name}_id 成对出现，避免 order_type/status_type 等字典字段误判
                if (preg_match('/_type$/', $column) && in_array(Str::substr($column, 0, -5).'_id', $columns)) {
                    continue;
                }

                return false;
            }

            return true;
        }

        return false;
    }

    private function getTableIndexes(string $tableName): array
    {
        $indexes = Schema::getIndexes($tableName);

        $columns = [];
        foreach ($indexes as $item) {
            // 复合索引按列展开（每列继承所属索引的 unique/primary 属性），避免仅保留最后一列
            foreach ($item['columns'] as $column) {
                $columns[] = [
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
            // 类型映射回调可选（字典文档等场景不需要 base_type/swagger_type）
            if ($callback !== null) {
                $column['base_type'] = $callback($column['type_name']);
                $column['swagger_type'] = $column['base_type'] === 'int' ? 'integer' : $column['base_type'];
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

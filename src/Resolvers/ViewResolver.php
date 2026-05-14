<?php

declare(strict_types=1);

namespace Juling\DevTools\Resolvers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use Juling\DevTools\Support\DevConfig;
use Juling\DevTools\Support\SchemaTrait;

class ViewResolver extends Foundation
{
    use SchemaTrait;

    public function build(DevConfig $devConfig, array $data): void
    {
        $comment = $data['tableComment'].'模块';
        $columns = $this->getTableColumns($data['tableName'], fn ($fieldType) => $this->getFieldType($fieldType));

        $this->tpl($devConfig, $data['tableName'], 'Index', $comment, $columns, 'index');
        $this->tpl($devConfig, $data['tableName'], 'Upsert', $comment, $columns, 'upsert');
    }

    private function tpl(DevConfig $devConfig, string $tableName, string $name, string $comment, array $columns, string $view): void
    {
        $outDir = $this->getTableGroupName($tableName);
        $subDir = Str::substr($tableName, Str::length($outDir) + 1);
        if (! empty($subDir)) {
            $outDir = Str::camel($outDir).'/'.Str::camel($subDir);
        } else {
            $outDir = Str::camel($outDir);
        }

        $dist = $devConfig->getDist('resources/vue/src/views/'.$outDir);
        $this->ensureDirectoryExists($dist);

        $primaryKey = $this->getTablePrimaryKey($tableName);
        $content = Blade::render(file_get_contents(__DIR__.'/stubs/view/'.$view.'.stub'), [
            'camelName' => Str::camel($tableName), // userAccount
            'snakeName' => Str::snake($tableName), // user_account
            'studlyName' => Str::studly($tableName), // UserAccount
            'primaryKey' => Str::camel($primaryKey), // actId
            'comment' => $comment,
            'columns' => $columns,
        ]);
        file_put_contents($dist.'/'.$name.'View.vue', $content);
    }
}

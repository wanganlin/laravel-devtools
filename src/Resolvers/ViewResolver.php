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
        $outDir = $this->getOutputDir($tableName);

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
        $this->writeFile($dist.'/'.$name.'View.vue', $content);
    }

    /**
     * 计算视图输出目录：组目录/子目录（如 categories_tags => category/tags）
     *
     * 按第一个下划线定位切分，不能用组名长度切割——categories 等不规则复数的
     * 单/复数长度不同（categories_tags 若按长度切会得到 sTags）
     */
    private function getOutputDir(string $tableName): string
    {
        $groupName = Str::camel($this->getTableGroupName($tableName));

        $pos = strpos($tableName, '_');
        if ($pos === false) {
            return $groupName;
        }

        return $groupName.'/'.Str::camel(Str::substr($tableName, $pos + 1));
    }
}

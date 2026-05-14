<?php

declare(strict_types=1);

namespace Juling\DevTools\Resolvers;

use Juling\DevTools\Support\DevConfig;
use Juling\DevTools\Support\SchemaTrait;

class DictResolver extends Foundation
{
    use SchemaTrait;

    public function build(DevConfig $devConfig, array $data): void
    {
        $dist = $devConfig->getDist('docs/dict');
        $this->ensureDirectoryExists($dist);

        $content = "# {$data['comment']}(`{$data['tableName']}`)\n\n";
        $columns = $this->getTableColumns($data['tableName']);
        $content .= $this->getContent($columns);

        file_put_contents($dist.'/'.$data['tableName'].'.md', $content);
    }

    public function getContent($columns): string
    {
        $content = "| 列名 | 数据类型 | 索引 | 是否为空 | 描述 |\n";
        $content .= "| ------- | --------- | --------- | --------- | -------------- |\n";
        foreach ($columns as $column) {
            $isNull = $column['nullable'] ? '是' : '否';
            $isIndex = $column['index'] ? '是' : '否';
            $content .= "| {$column['name']} | {$column['type']} | {$isIndex} | $isNull | {$column['comment']} |\n";
        }
        $content .= "\n";

        return $content;
    }
}

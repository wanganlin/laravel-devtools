<?php

declare(strict_types=1);

namespace Juling\DevTools\Resolvers;

abstract class Foundation
{
    /**
     * 是否强制覆盖已存在的文件（由命令行 --force 选项注入）
     */
    public bool $force = false;

    /**
     * 写入生成文件：默认跳过已存在文件以保护手写代码，force 时覆盖
     */
    protected function writeFile(string $path, string $content): bool
    {
        if (! $this->force && is_file($path)) {
            return false;
        }

        file_put_contents($path, $content);

        return true;
    }

    public function getFieldType(string $fieldType): string
    {
        preg_match('/(\w+)\(/', $fieldType, $m);
        $type = $m[1] ?? $fieldType;
        $type = str_replace(' unsigned', '', $type);
        if (in_array($type, ['bit', 'int', 'bigint', 'mediumint', 'smallint', 'tinyint', 'enum'])) {
            $type = 'int';
        }
        if (in_array($type, ['varchar', 'char', 'text', 'mediumtext', 'longtext', 'decimal'])) {
            $type = 'string';
        }
        if (in_array($type, ['float', 'double'])) {
            $type = 'float';
        }
        if (in_array($type, ['date', 'datetime', 'timestamp', 'time'])) {
            $type = 'string';
        }
        if (! in_array($type, ['int', 'string', 'float'])) {
            $type = 'string';
        }

        return $type;
    }
}

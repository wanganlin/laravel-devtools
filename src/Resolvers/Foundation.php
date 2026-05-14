<?php

declare(strict_types=1);

namespace Juling\DevTools\Resolvers;

abstract class Foundation
{
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

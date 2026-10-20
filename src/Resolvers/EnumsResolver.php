<?php

declare(strict_types=1);

namespace Juling\DevTools\Resolvers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use Juling\DevTools\Support\DevConfig;
use Juling\DevTools\Support\SchemaTrait;

class EnumsResolver extends Foundation
{
    use SchemaTrait;

    public function build(DevConfig $devConfig, array $data): void
    {
        if ($devConfig->getMultiModule()) {
            $groupName = $this->getTableGroupName($data['tableName']);
            $dist = $devConfig->getDist('app/Domains/'.$groupName.'/Enums');
            $data['namespace'] = "App\\Domains\\$groupName";
        } else {
            $dist = $devConfig->getDist('app/Enums');
            $data['namespace'] = 'App';
        }
        $this->ensureDirectoryExists($dist);

        $data['tableColumns'] = $this->getTableColumns($data['tableName'], fn ($fieldType) => $this->getFieldType($fieldType));
        foreach ($data['tableColumns'] as $column) {
            if (! in_array($column['type_name'], ['enum', 'tinyint'], true)) {
                continue;
            }

            $parsed = $this->parseComment($column['comment']);
            if ($parsed === null) {
                continue;
            }

            $this->writeEnumClass($data, $dist, $column, $parsed[0], $parsed[1]);
        }
    }

    /**
     * 解析字段注释中的枚举定义，格式：枚举名:值-标签,值-标签
     *
     * @return array{0: string, 1: list<array{0: string, 1: string}>}|null 无有效选项时返回 null
     */
    private function parseComment(string $comment): ?array
    {
        $comment = Str::replace(['：', '，'], [':', ','], $comment);

        $split = explode(':', $comment, 2);
        if (count($split) < 2) {
            return null;
        }
        [$enumsName, $options] = $split;

        $enumsOptions = [];
        foreach (explode(',', $options) as $option) {
            if (! Str::contains($option, '-')) {
                continue;
            }

            [$value, $label] = explode('-', $option, 2);
            if ($value === '' || $label === '') {
                continue;
            }

            $enumsOptions[] = [$value, $label];
        }

        if ($enumsOptions === []) {
            return null;
        }

        return [$enumsName, $enumsOptions];
    }

    /**
     * @param  list<array{0: string, 1: string}>  $enumsOptions
     */
    private function writeEnumClass(array $data, string $dist, array $column, string $enumsName, array $enumsOptions): void
    {
        $enumsClass = Str::studly($this->convertToSingular($column['name']));
        $isInt = $this->isIntEnum($column['type_name'], $enumsOptions);

        $enums = '';
        foreach ($enumsOptions as [$value, $label]) {
            $caseName = $isInt ? $enumsClass.$value : $this->toCaseName($enumsClass, $value);
            $caseValue = $isInt ? $value : "'".str_replace("'", "\\'", $value)."'";
            $enums .= <<<EOF


    /**
     * $label
     */
    case $caseName = $caseValue;
EOF;
        }

        $data = array_merge($data, [
            'comment' => $enumsName,
            'className' => $data['className'].$enumsClass,
            'enums' => $enums,
            'enumsType' => $isInt ? 'int' : 'string',
        ]);

        $tpl = file_get_contents(__DIR__.'/stubs/enums/enums.stub');
        $content = Blade::render($tpl, $data, deleteCachedView: true);
        $this->writeFile($dist.'/'.$data['className'].'Enum.php', "<?php\n\n".$content);
    }

    /**
     * tinyint 且所有取值均为数字时使用 int 作为枚举底层类型，否则使用 string
     *
     * @param  list<array{0: string, 1: string}>  $enumsOptions
     */
    private function isIntEnum(string $typeName, array $enumsOptions): bool
    {
        if ($typeName !== 'tinyint') {
            return false;
        }

        foreach ($enumsOptions as [$value]) {
            if (! is_numeric($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 生成合法的枚举成员名：以字母/下划线开头，否则加上枚举类名前缀（如 1 -> Status1）
     */
    private function toCaseName(string $enumsClass, string $value): string
    {
        $caseName = Str::studly($value);
        if ($caseName === '' || ! preg_match('/^[a-zA-Z_\x80-\xfe]/', $caseName)) {
            return $enumsClass.$caseName;
        }

        return $caseName;
    }
}

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
            $dist = $devConfig->getDist('app/Bundles/'.$groupName.'/Enums');
            $data['namespace'] = "App\\Bundles\\$groupName";
        } else {
            $dist = $devConfig->getDist('app/Enums');
            $data['namespace'] = 'App';
        }
        $this->ensureDirectoryExists($dist);

        $data['tableColumns'] = $this->getTableColumns($data['tableName'], fn ($fieldType) => $this->getFieldType($fieldType));

        foreach ($data['tableColumns'] as $column) {
            if ($column['type'] === 'enum' || $column['type_name'] === 'tinyint') {
                $enumsClass = Str::studly($this->getSingular($column['name']));
                $comment = Str::replace('：', ':', $column['comment']);
                $comment = Str::replace('，', ',', $comment);

                $split = explode(':', $comment);
                if (count($split) < 2) {
                    continue;
                }

                [$enumsName, $enumsOptions] = $split;
                $enumsOptions = explode(',', $enumsOptions);
                $enumsOptions = array_map(function ($enumsOption) {
                    if (Str::contains($enumsOption, '-')) {
                        return explode('-', $enumsOption);
                    } else {
                        preg_match('/^(\d+)(.*)$/', $enumsOption, $matches);

                        return [$matches[1], $matches[2]];
                    }
                }, $enumsOptions);

                $enums = '';
                $enumsType = 'int';
                foreach ($enumsOptions as $enumOption) {
                    $caseName = $enumsClass.$enumOption[0];
                    $caseVal = $enumOption[0];
                    $enums .= <<<EOF


    /**
     * $enumOption[1]
     */
    case $caseName = $caseVal;
EOF;
                    if (! is_numeric($caseVal)) {
                        $enumsType = 'string';
                    }
                }

                $data['comment'] = $enumsName;
                $data['className'] = $data['className'].$enumsClass;
                $data['enums'] = $enums;
                $data['enumsType'] = $enumsType;

                $tpl = file_get_contents(__DIR__.'/stubs/enums/enums.stub');
                $content = Blade::render($tpl, $data, deleteCachedView: true);
                file_put_contents($dist.'/'.$data['className'].'Enum.php', "<?php\n\n".$content);
            }
        }
    }
}

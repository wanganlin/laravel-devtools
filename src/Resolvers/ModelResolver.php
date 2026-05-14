<?php

declare(strict_types=1);

namespace Juling\DevTools\Resolvers;

use Illuminate\Support\Facades\Blade;
use Juling\DevTools\Support\DevConfig;
use Juling\DevTools\Support\SchemaTrait;

class ModelResolver extends Foundation
{
    use SchemaTrait;

    public function build(DevConfig $devConfig, array $data): void
    {
        if ($devConfig->getMultiModule()) {
            $groupName = $this->getTableGroupName($data['tableName']);
            $dist = $devConfig->getDist('app/Bundles/'.$groupName.'/Models');
            $data['namespace'] = "App\\Bundles\\$groupName";
        } else {
            $dist = $devConfig->getDist('app/Models');
            $data['namespace'] = 'App';
        }
        $this->ensureDirectoryExists($dist);

        $data['primaryKey'] = $this->getTablePrimaryKey($data['tableName']);
        $data['tableColumns'] = $this->getTableColumns($data['tableName'], fn ($fieldType) => $this->getFieldType($fieldType));
        $data['softDelete'] = false;

        $ignoreColumns = array_merge($devConfig->getIgnoreColumns($data['tableName']), [$data['primaryKey']]);
        foreach ($data['tableColumns'] as $key => $column) {
            if (in_array($column['name'], $ignoreColumns)) {
                unset($data['tableColumns'][$key]);

                continue;
            }
            if ($column['name'] === 'deleted_at') {
                $data['softDelete'] = true;
            }
        }

        $tpl = file_get_contents(__DIR__.'/stubs/model/model.stub');
        $content = Blade::render($tpl, $data, deleteCachedView: true);
        file_put_contents($dist.'/'.$data['className'].'.php', "<?php\n\n".$content);
    }
}

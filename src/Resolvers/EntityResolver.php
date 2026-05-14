<?php

declare(strict_types=1);

namespace Juling\DevTools\Resolvers;

use Illuminate\Support\Facades\Blade;
use Juling\DevTools\Support\DevConfig;
use Juling\DevTools\Support\SchemaTrait;

class EntityResolver extends Foundation
{
    use SchemaTrait;

    public function build(DevConfig $devConfig, array $data): void
    {
        if ($devConfig->getMultiModule()) {
            $groupName = $this->getTableGroupName($data['tableName']);
            $dist = $devConfig->getDist('app/Bundles/'.$groupName.'/Entities');
            $data['namespace'] = "App\\Bundles\\$groupName";
        } else {
            $dist = $devConfig->getDist('app/Entities');
            $data['namespace'] = 'App';
        }
        $this->ensureDirectoryExists($dist);

        $data['tableColumns'] = $this->getTableColumns($data['tableName'], fn ($fieldType) => $this->getFieldType($fieldType));

        $tpl = file_get_contents(__DIR__.'/stubs/entity/entity.stub');
        $content = Blade::render($tpl, $data, deleteCachedView: true);
        file_put_contents($dist.'/'.$data['className'].'Entity.php', "<?php\n\n".$content);
    }
}

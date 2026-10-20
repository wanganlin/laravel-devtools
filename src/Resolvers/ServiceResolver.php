<?php

declare(strict_types=1);

namespace Juling\DevTools\Resolvers;

use Illuminate\Support\Facades\Blade;
use Juling\DevTools\Support\DevConfig;
use Juling\DevTools\Support\SchemaTrait;

class ServiceResolver extends Foundation
{
    use SchemaTrait;

    public function build(DevConfig $devConfig, array $data): void
    {
        if ($devConfig->getMultiModule()) {
            $groupName = $this->getTableGroupName($data['tableName']);
            $dist = $devConfig->getDist('app/Domains/'.$groupName.'/Services');
            $data['namespace'] = "App\\Domains\\$groupName\\Services";
            $data['useNamespace'] = "App\\Domains\\$groupName";
        } else {
            $dist = $devConfig->getDist('app/Services');
            $data['namespace'] = 'App\\Services';
            $data['useNamespace'] = 'App';
        }
        $this->ensureDirectoryExists($dist);

        $tpl = file_get_contents(__DIR__.'/stubs/service/service.stub');
        $content = Blade::render($tpl, $data, deleteCachedView: true);
        $this->writeFile($dist.'/'.$data['className'].'Service.php', "<?php\n\n".$content);
    }
}

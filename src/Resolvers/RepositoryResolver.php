<?php

declare(strict_types=1);

namespace Juling\DevTools\Resolvers;

use Illuminate\Support\Facades\Blade;
use Juling\DevTools\Support\DevConfig;
use Juling\DevTools\Support\SchemaTrait;

class RepositoryResolver extends Foundation
{
    use SchemaTrait;

    public function build(DevConfig $devConfig, array $data): void
    {
        if ($devConfig->getMultiModule()) {
            $groupName = $this->getTableGroupName($data['tableName']);
            $dist = $devConfig->getDist('app/Bundles/'.$groupName.'/Repositories');
            $data['namespace'] = "App\\Bundles\\$groupName";
        } else {
            $dist = $devConfig->getDist('app/Repositories');
            $data['namespace'] = 'App';
        }
        $this->ensureDirectoryExists($dist);

        $tpl = file_get_contents(__DIR__.'/stubs/repository/repository.stub');
        $content = Blade::render($tpl, $data, deleteCachedView: true);
        file_put_contents($dist.'/'.$data['className'].'Repository.php', "<?php\n\n".$content);
    }
}

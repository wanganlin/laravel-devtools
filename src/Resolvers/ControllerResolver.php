<?php

declare(strict_types=1);

namespace Juling\DevTools\Resolvers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use Juling\DevTools\Support\DevConfig;
use Juling\DevTools\Support\SchemaTrait;

class ControllerResolver extends Foundation
{
    use SchemaTrait;

    private array $ignoreFields = ['id', 'created_at', 'updated_at', 'deleted_at'];

    public function build(DevConfig $devConfig, array $data): void
    {
        $columns = $this->getTableColumns($data['tableName'], fn ($fieldType) => $this->getFieldType($fieldType));
        $indexes = $this->getTableIndexes($data['tableName']);

        $this->controllerTpl($data['className'], $indexes, $data['tableComment'], 'Admin');
        $this->queryRequestTpl($data['className'], $columns, $indexes, 'Admin');
        $this->createRequest($data['className'], $columns, $indexes, 'Admin');
        $this->updateRequest($data['className'], $columns, $indexes, 'Admin');
        $this->destroyRequest($data['className'], $columns, $indexes, 'Admin');
        $this->responseTpl($data['className'], $columns, $indexes, 'Admin');
    }

    private function controllerTpl(string $className, array $indexes, string $comment, string $outDir): void
    {
        $groupName = $this->getTableGroupName(Str::snake($className));

        $devConfig = new DevConfig;
        if ($devConfig->getMultiModule()) {
            $dist = $devConfig->getDist('app/Bundles/'.$groupName.'/Controllers');
            $namespace = "App\\Bundles\\$groupName";
            $entityNamespace = "App\\Bundles\\$groupName\\Entities";
            $serviceNamespace = "App\\Bundles\\$groupName\\Services";
            $serviceName = $className.'Bundle';
        } else {
            $dist = $devConfig->getDist('app/Api/'.$outDir.'/Controllers');
            $namespace = 'App\\Api\\'.$outDir;
            $entityNamespace = 'App\\Entities';
            $serviceNamespace = 'App\\Services';
            $serviceName = $className;
        }

        $this->ensureDirectoryExists($dist);

        $content = Blade::render(file_get_contents(__DIR__.'/stubs/controller/controller.stub'), [
            'namespace' => $namespace,
            'entityNamespace' => $entityNamespace,
            'serviceNamespace' => $serviceNamespace,
            'className' => $className,
            'groupName' => $groupName,
            'classCamelName' => Str::camel($className),
            'serviceName' => $serviceName,
            'serviceCamelName' => Str::camel($serviceName),
            'tableIndexes' => $indexes,
            'comment' => $comment,
        ], deleteCachedView: true);

        file_put_contents($dist.'/'.$className.'Controller.php', "<?php\n\n".$content);
    }

    private function queryRequestTpl(string $className, array $columns, array $indexes, string $outDir): void
    {
        $queryRequests = array_map(function ($column) {
            return $column['name'];
        }, $indexes);

        foreach ($columns as $key => $column) {
            if (! in_array($column['name'], $queryRequests)) {
                unset($columns[$key]);
            }
        }

        $this->writeRequest($className, 'QueryRequest', [
            'className' => $className,
            'schema' => $className.'QueryRequest',
            'tableColumns' => $columns,
            'tableIndexes' => $indexes,
        ], $outDir);
    }

    private function createRequest(string $className, array $columns, array $indexes, string $outDir): void
    {
        foreach ($columns as $key => $column) {
            if (in_array($column['name'], $this->ignoreFields)) {
                unset($columns[$key]);
            }
        }

        $this->writeRequest($className, 'CreateRequest', [
            'className' => $className,
            'schema' => $className.'CreateRequest',
            'tableColumns' => $columns,
            'tableIndexes' => $indexes,
        ], $outDir);
    }

    private function updateRequest(string $className, array $columns, array $indexes, string $outDir): void
    {
        $this->ignoreFields = array_slice($this->ignoreFields, 1);
        foreach ($columns as $key => $column) {
            if (in_array($column['name'], $this->ignoreFields)) {
                unset($columns[$key]);
            }
        }

        $this->writeRequest($className, 'UpdateRequest', [
            'className' => $className,
            'schema' => $className.'UpdateRequest',
            'tableColumns' => $columns,
            'tableIndexes' => $indexes,
        ], $outDir);
    }

    private function destroyRequest(string $className, array $columns, array $indexes, string $outDir): void
    {
        $this->writeRequest($className, 'DestroyRequest', [
            'className' => $className,
            'schema' => $className.'DestroyRequest',
            'tableColumns' => $columns,
            'tableIndexes' => $indexes,
        ], $outDir);
    }

    private function writeRequest(string $className, string $suffix, array $data, string $outDir): void
    {
        $devConfig = new DevConfig;
        if ($devConfig->getMultiModule()) {
            $groupName = $this->getTableGroupName(Str::snake($className));
            $dist = $devConfig->getDist('app/Bundles/'.$groupName.'/Requests/'.$className);
            $namespace = "App\\Bundles\\$groupName";
        } else {
            $dist = $devConfig->getDist('app/Api/'.$outDir.'/Requests/'.$className);
            $namespace = 'App\\Api\\'.$outDir;
        }
        $this->ensureDirectoryExists($dist);

        $data = array_merge([
            'namespace' => $namespace,
        ], $data);

        $content = Blade::render(file_get_contents(__DIR__.'/stubs/request/'.Str::snake($suffix).'.stub'), $data, deleteCachedView: true);
        file_put_contents($dist.'/'.$className.$suffix.'.php', "<?php\n\n".$content);
    }

    private function responseTpl(string $className, array $columns, array $indexes, string $outDir): void
    {
        $devConfig = new DevConfig;
        if ($devConfig->getMultiModule()) {
            $groupName = $this->getTableGroupName(Str::snake($className));
            $dist = $devConfig->getDist('app/Bundles/'.$groupName.'/Responses/'.$className);
            $namespace = "App\\Bundles\\$groupName";
        } else {
            $dist = $devConfig->getDist('app/Api/'.$outDir.'/Responses/'.$className);
            $namespace = 'App\\Api\\'.$outDir;
        }
        $this->ensureDirectoryExists($dist);

        $content = Blade::render(file_get_contents(__DIR__.'/stubs/response/query.stub'), [
            'namespace' => $namespace,
            'className' => $className,
        ], deleteCachedView: true);
        file_put_contents($dist.'/'.$className.'QueryResponse.php', "<?php\n\n".$content);

        $content = Blade::render(file_get_contents(__DIR__.'/stubs/response/destroy.stub'), [
            'namespace' => $namespace,
            'className' => $className,
        ], deleteCachedView: true);
        file_put_contents($dist.'/'.$className.'DestroyResponse.php', "<?php\n\n".$content);

        $ignoreFields = ['deleted_time', 'password', 'password_salt'];
        foreach ($columns as $key => $column) {
            if (in_array($column['name'], $ignoreFields)) {
                unset($columns[$key]);
            }
        }

        $content = Blade::render(file_get_contents(__DIR__.'/stubs/response/response.stub'), [
            'namespace' => $namespace,
            'className' => $className,
            'tableColumns' => $columns,
        ], deleteCachedView: true);
        file_put_contents($dist.'/'.$className.'Response.php', "<?php\n\n".$content);
    }
}

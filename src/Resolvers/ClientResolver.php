<?php

declare(strict_types=1);

namespace Juling\DevTools\Resolvers;

use Illuminate\Support\Str;
use Juling\DevTools\Support\DevConfig;
use Juling\DevTools\Support\SchemaTrait;

class ClientResolver extends Foundation
{
    use SchemaTrait;

    private string $dist;

    public function build(DevConfig $devConfig, array $data): void
    {
        $files = glob(base_path('docs/api/*.json'));
        foreach ($files as $file) {
            $serviceName = Str::studly(basename(dirname(__DIR__, 5)));
            $moduleName = Str::studly(basename($file, '.json'));

            $this->dist = $devConfig->getDist('resources/client/src/'.$moduleName);
            $this->ensureDirectoryExists($this->dist.'/Model');

            $data = json_decode(file_get_contents($file), true);
            $this->genModels($serviceName, $moduleName, $data);

            $content = $this->genClient($serviceName, $moduleName, $data);
            file_put_contents("$this->dist/{$moduleName}Svc.php", $content);
        }
    }

    private function genClient(string $serviceName, string $moduleName, array $data): string
    {
        $content = <<<EOF
<?php

declare(strict_types=1);

namespace $serviceName\\Client\\$moduleName;

use Exception;
use $serviceName\\Client\\Support\\SvcClient;
{{ using }}

class {$moduleName}Svc
{
    use SvcClient;
\n
EOF;

        if (isset($data['paths'])) {
            $apis = []; // API接口
            $types = []; // 参数类型
            foreach ($data['paths'] as $path => $item) {
                $requestParams = '';
                $requestBody = '';

                foreach ($item as $method => $val) {
                    // query 参数
                    if (isset($val['parameters'])) {
                        $parameters = [];
                        foreach ($val['parameters'] as $v) {
                            $vType = 'string';
                            if (isset($v['example']) && is_int($v['example'])) {
                                $vType = 'int';
                            }
                            $parameters[$v['name']] = $vType;
                        }

                        $params = [];
                        foreach ($parameters as $k => $t) {
                            $params[] = $t.' $'.$k;
                        }

                        $requestParams = implode(', ', $params);

                        $requestBody = '';
                        foreach ($parameters as $k => $t) {
                            $requestBody .= "'&{$k}='.\$".$k.'.';
                        }
                        $requestBody = '.\'?'.Str::substr($requestBody, 2, -1);
                    }

                    // formData 参数
                    if (isset($val['requestBody']['content']['application/json']['schema']['$ref'])) {
                        $request = $val['requestBody']['content']['application/json']['schema']['$ref'];
                        preg_match('/\/components\/schemas\/(\w+)/', $request, $m);
                        if (isset($m[1])) {
                            $interface = $m[1];
                            $types[] = $interface;

                            if (empty($requestParams)) {
                                $requestParams .= $interface.' $formData';
                            } else {
                                $requestParams .= ', '.$interface.' $formData';
                            }

                            $requestBody .= ', $formData->toArray()';
                        }
                    }

                    // 文件上传
                    if (isset($val['requestBody']['content']['multipart/form-data']['schema']['$ref'])) {
                        $request = $val['requestBody']['content']['multipart/form-data']['schema']['$ref'];
                        preg_match('/\/components\/schemas\/(\w+)/', $request, $m);
                        if (isset($m[1])) {
                            $interface = 'I'.$m[1];
                            $types[] = $interface;

                            if (empty($requestParams)) {
                                $requestParams .= $interface.' $formData';
                            } else {
                                $requestParams .= ', '.$interface.' $formData';
                            }

                            $requestBody .= ', $formData->toArray()';
                            $requestBody .= ", headers: { 'Content-Type': 'multipart/form-data' }";
                        }
                    }

                    $response = 'mixed';
                    if (isset($val['responses'][200]['content']['application/json']['schema']['$ref'])) {
                        $response = $val['responses'][200]['content']['application/json']['schema']['$ref'];
                        preg_match('/\/components\/schemas\/(\w+)/', $response, $m);
                        if (isset($m[1])) {
                            $interface = $m[1];
                            $types[] = $interface;
                            $response = $interface;
                        }
                    }

                    $svc = Str::camel($serviceName);
                    $svcMethod = Str::camel(Str::replace('/', ' ', $path));
                    $mod = Str::camel($moduleName);

                    if (empty($svcMethod)) {
                        $svcMethod = 'index';
                    }

                    if ($response === 'mixed') {
                        $resultData = "\$result['data']";
                    } else {
                        $resultData = "new {$response}(\$result['data'])";
                    }

                    $apis[] = "    /**
     * [{$val['tags'][0]}] {$val['summary']}
     *
     * @throws Exception
     */
    public function {$svcMethod}({$requestParams}): {$response}
    {
        \$url = '/api/{$mod}{$path}';
        \$result = \$this->svc('{$svc}')->{$method}(\$url{$requestBody})->json();
        if (\$result['code'] !== 0) {
            throw new Exception(\$result['message']);
        }

        return {$resultData};
    }\n";
                }
            }

            $models = array_map(function ($v) use ($serviceName, $moduleName) {
                return "use $serviceName\\Client\\$moduleName\\Model\\".$v.';';
            }, array_unique($types));
            $content = str_replace('{{ using }}', implode("\n", $models), $content);

            $content .= implode("\n", $apis);
        }

        $content .= <<<'EOF'
}
EOF;

        return $content;
    }

    private function genModels(string $serviceName, string $moduleName, array $data): string
    {
        $content = '';
        if (isset($data['components']['schemas'])) {
            foreach ($data['components']['schemas'] as $type => $schema) {
                if (Str::contains($type, 'Schema')) {
                    continue;
                }
                if (! isset($schema['properties'])) {
                    exit($moduleName.' 模块中的 '.$type.' 缺少 properties 参数');
                }
                $content .= $this->genModel($serviceName, $moduleName, $type, $schema);
            }
        }

        return $content;
    }

    private function genModel(string $serviceName, string $moduleName, string $modelName, array $schema): string
    {
        $getSet = [];
        $c = <<<EOF
<?php

declare(strict_types=1);

namespace $serviceName\\Client\\$moduleName\\Model;

use Juling\Foundation\Support\DTOHelper;

class $modelName
{
    use DTOHelper;
\n
EOF;

        foreach ($schema['properties'] as $name => $property) {
            if (isset($property['type'])) {
                $type = $property['type'];
                if ($type === 'integer') {
                    $type = 'int';
                } elseif ($type === 'boolean') {
                    $type = 'bool';
                } elseif ($type === 'file') {
                    $type = 'string';
                } elseif ($type === 'array') {
                    if (isset($property['items']['type'])) {
                        $type = $property['items']['type'];
                        if ($type === 'integer') {
                            $type = 'int';
                        } elseif ($type === 'boolean') {
                            $type = 'bool';
                        } elseif ($type === 'file') {
                            $type = 'string';
                        }
                    }
                }
            } elseif (isset($property['$ref'])) {
                $type = 'array';
            } else {
                exit($modelName.' 对象 '.var_export($property, true).' 缺失类型');
            }

            $description = isset($property['description']) ? ' // '.$property['description'] : '';

            if (isset($schema['required']) && ! in_array($name, $schema['required'])) {
                $type = '?'.$type;
            }

            $c .= "    private $type \$$name;$description\n\n";

            $studlyName = Str::studly($name);
            $getSet[] = <<<EOF
    public function get{$studlyName}(): $type
    {
        return \$this->$name;
    }

    public function set{$studlyName}($type \$$name): void
    {
        \$this->$name = \$$name;
    }
EOF;
        }

        $c .= implode("\n\n", $getSet);

        $c .= "\n}\n\n";

        file_put_contents($this->dist.'/Model/'.$modelName.'.php', $c);

        return $c;
    }
}

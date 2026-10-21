<?php

declare(strict_types=1);

namespace Juling\DevTools\Resolvers;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Juling\DevTools\Support\DevConfig;
use Juling\DevTools\Support\SchemaTrait;
use RuntimeException;

class TypescriptResolver extends Foundation
{
    use SchemaTrait;

    private string $tsDist;

    public function build(DevConfig $devConfig, array $data): void
    {
        $this->tsDist = $devConfig->getDist('packages');

        $files = glob(base_path('docs/api/*.json'));
        foreach ($files as $file) {
            $module = basename($file, '.json');
            $data = json_decode(file_get_contents($file), true);
            $this->genServices($data, $module);
        }
    }

    public function genServices(array $data, string $module): void
    {
        if (isset($data['paths'])) {
            $groupApis = []; // API接口
            $groupTypes = []; // 参数类型
            $groupHelpers = []; // 各分组用到的请求助手函数
            foreach ($data['paths'] as $path => $item) {
                $group = Arr::first(explode('/', ltrim($path, '/')));

                foreach ($item as $method => $val) {
                    // 助手函数位置参数：$dataArg 为请求体（第二参），$configParts 为 query/headers（归入 config）
                    $requestParams = '';
                    $dataArg = '';
                    $configParts = [];

                    // query 参数
                    if (isset($val['parameters'])) {
                        $parameters = [];
                        foreach ($val['parameters'] as $v) {
                            $vType = 'string';
                            if (isset($v['example']) && is_int($v['example'])) {
                                $vType = 'number';
                            }
                            $parameters[$v['name']] = $vType;
                        }

                        $params = [];
                        foreach ($parameters as $k => $t) {
                            $params[] = $k.': '.$t;
                        }

                        $requestParams = implode(', ', $params);
                        $configParts[] = 'params: { '.implode(', ', array_keys($parameters)).' }';
                    }

                    // formData 参数
                    if (isset($val['requestBody']['content']['application/json']['schema']['$ref'])) {
                        $request = $val['requestBody']['content']['application/json']['schema']['$ref'];
                        preg_match('/\/components\/schemas\/(\w+)/', $request, $m);
                        if (isset($m[1])) {
                            $interface = 'I'.$m[1];
                            $groupTypes[$group][] = $interface;

                            if (empty($requestParams)) {
                                $requestParams .= 'data: '.$interface;
                            } else {
                                $requestParams .= ', data: '.$interface;
                            }

                            $dataArg = 'data';
                        }
                    } elseif (isset($val['requestBody']['content']['application/json']['schema']['items']['type'])) {
                        $request = $val['requestBody']['content']['application/json']['schema']['items']['type'];
                        $requestParams = 'id: number[]';
                        $configParts[] = 'params: { id }';
                    }

                    // 文件上传
                    if (isset($val['requestBody']['content']['multipart/form-data']['schema']['$ref'])) {
                        $request = $val['requestBody']['content']['multipart/form-data']['schema']['$ref'];
                        preg_match('/\/components\/schemas\/(\w+)/', $request, $m);
                        if (isset($m[1])) {
                            $interface = 'I'.$m[1];
                            $groupTypes[$group][] = $interface;

                            if (empty($requestParams)) {
                                $requestParams .= 'data: '.$interface;
                            } else {
                                $requestParams .= ', data: '.$interface;
                            }

                            $dataArg = 'data';
                            $configParts[] = "headers: { 'Content-Type': 'multipart/form-data' }";
                        }
                    }

                    $response = '<any>';
                    if (isset($val['responses'][200]['content']['application/json']['schema']['$ref'])) {
                        $response = $val['responses'][200]['content']['application/json']['schema']['$ref'];
                        preg_match('/\/components\/schemas\/(\w+)/', $response, $m);
                        if (isset($m[1])) {
                            $interface = 'I'.$m[1];
                            $groupTypes[$group][] = $interface;
                            $response = '<'.$interface.'>';
                        }
                    } elseif (isset($val['responses'][200]['content']['application/json']['schema']['properties']['data']['$ref'])) {
                        $response = $val['responses'][200]['content']['application/json']['schema']['properties']['data']['$ref'];
                        preg_match('/\/components\/schemas\/(\w+)/', $response, $m);
                        if (isset($m[1])) {
                            $interface = 'I'.$m[1];
                            $groupTypes[$group][] = $interface;
                            $response = '<'.$interface.'>';
                        }
                    } elseif (isset($val['responses'][200]['content']['application/json']['schema']['items']['$ref'])) {
                        $response = $val['responses'][200]['content']['application/json']['schema']['items']['$ref'];
                        preg_match('/\/components\/schemas\/(\w+)/', $response, $m);
                        if (isset($m[1])) {
                            $interface = 'I'.$m[1];
                            $groupTypes[$group][] = $interface;
                            $response = '<'.$interface.'[]>';
                        }
                    }

                    $service = Str::camel(Str::replace('/', ' ', $path));
                    $url = (Str::substr($path, 0, 1) === '/') ? $module.$path : $module.'/'.$path;

                    $helper = match ($method) {
                        'get' => 'get', 'post' => 'post', 'put' => 'put', 'delete' => 'del',
                        default => throw new RuntimeException("不支持的请求方法：{$method}"),
                    };
                    $groupHelpers[$group][] = $helper;

                    $args = "'{$url}'".($dataArg !== '' ? ", {$dataArg}" : '');
                    if ($configParts !== []) {
                        $args .= ', { '.implode(', ', $configParts).' }';
                    }

                    $groupApis[$group][] = "/** [{$val['tags'][0]}] {$val['summary']} */
export function {$service}Api({$requestParams}): Promise{$response} {
  return {$helper}{$response}({$args})
}\n";
                }
            }

            foreach ($groupApis as $group => $apis) {
                if (empty($group)) {
                    continue;
                }

                $servicePath = $this->tsDist.'/'.$module.'/src/api';
                $this->ensureDirectoryExists($servicePath);

                $helpers = array_unique($groupHelpers[$group] ?? []);
                $content = 'import { '.implode(', ', $helpers)." } from '@/utils/request'\n";
                if (isset($groupTypes[$group])) {
                    $this->genTypes($data, $module, $group, $groupTypes[$group]);
                    $content .= 'import type { '.implode(', ', array_unique($groupTypes[$group]))." } from '@/types/{$group}'\n\n";
                }
                $content .= implode("\n", $apis);

                file_put_contents($servicePath.'/'.$group.'.ts', $content);
            }
        }
    }

    private function genTypes(array $data, string $module, string $group, array $types): void
    {
        $contents[$group] = '';
        if (isset($data['components']['schemas'])) {
            foreach ($data['components']['schemas'] as $type => $schema) {
                if (Str::contains($type, 'Schema') || ! in_array('I'.$type, $types)) {
                    continue;
                }
                if (! isset($schema['properties'])) {
                    throw new RuntimeException($module.' 模块中的 '.$type.' 缺少 properties 参数');
                }
                $contents[$group] .= $this->genTypeSchemas($type, $schema);
            }
        }

        foreach ($contents as $group => $content) {
            $typePath = $this->tsDist.'/'.$module.'/src/types';
            $this->ensureDirectoryExists($typePath);
            file_put_contents($typePath.'/'.$group.'.d.ts', $content);
        }
    }

    private function genTypeSchemas(string $interface, array $schema): string
    {
        $c = "export interface I$interface {\n";

        foreach ($schema['properties'] as $name => $property) {
            if (isset($property['type'])) {
                $type = $property['type'];
                if (in_array($type, ['integer', 'float'])) {
                    $type = 'number';
                } elseif ($type === 'file') {
                    $type = 'string';
                } elseif ($type === 'array') {
                    if (isset($property['items']['$ref'])) {
                        $type = 'I'.basename($property['items']['$ref']).'[]';
                    } elseif (isset($property['items']['type'])) {
                        $type = $property['items']['type'];
                        if (in_array($type, ['integer', 'float'])) {
                            $type = 'number';
                        }
                        $type = $type.'[]';
                    }
                }
            } elseif (isset($property['$ref'])) {
                $type = 'I'.basename($property['$ref']);
            } else {
                throw new RuntimeException($interface.' 对象 '.var_export($property, true).' 缺失类型');
            }

            $description = isset($property['description']) ? ' // '.$property['description'] : '';

            if (isset($schema['required']) && ! in_array($name, $schema['required'])) {
                $name = $name.'?';
            }

            $c .= "  $name: $type,$description\n";
        }

        return $c."}\n\n";
    }
}

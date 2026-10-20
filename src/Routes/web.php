<?php

use Illuminate\Support\Facades\Route;
use OpenApi\Generator;

Route::prefix('swagger-ui')->group(function () {
    // 显示swagger UI界面（扫描 Api/Domains/Modules 三种模块布局）
    Route::get('/', function () {
        $apis = [];
        foreach (['Api', 'Domains', 'Modules'] as $root) {
            foreach (\glob(app_path($root.'/*'), GLOB_ONLYDIR) ?: [] as $path) {
                $apis[\basename($path)] = '/swagger-ui/openapi/'.\basename($path).'.json';
            }
        }

        return view('__foundation__::swagger-ui', ['apis' => $apis]);
    });

    // 生成api文档
    Route::get('openapi/{api}.json', function (string $api) {
        foreach (['Api', 'Domains', 'Modules'] as $root) {
            $apiPath = app_path($root.'/'.$api);
            if (! \is_dir($apiPath)) {
                continue;
            }

            $openapi = (new Generator)->generate([$apiPath, app_path('Http/Responses')]);

            return response()->json(json_decode($openapi->toJson(), true));
        }

        throw new Exception('Api模块不存在');
    });
});

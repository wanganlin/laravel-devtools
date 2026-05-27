<?php

use Illuminate\Support\Facades\Route;
use OpenApi\Generator;

Route::prefix('swagger-ui')->group(function () {
    // 显示swagger UI界面
    Route::get('/', function () {
        $apis = [];
        $dirs = \glob(app_path('Api/*'), GLOB_ONLYDIR);
        foreach ($dirs as $path) {
            $name = \basename($path);
            $apis[$name] = '/swagger-ui/openapi/'.$name.'.json';
        }

        return view('__foundation__::swagger-ui', ['apis' => $apis]);
    });

    // 生成api文档
    Route::get('openapi/{api}.json', function (string $api) {
        $apiPath = app_path('Api/'.$api);
        if (! \is_dir($apiPath)) {
            throw new Exception('Api模块不存在');
        }

        $openapi = (new Generator)->generate([$apiPath, app_path('Http/Responses')]);

        return response()->json(json_decode($openapi->toJson(), true));
    });
});

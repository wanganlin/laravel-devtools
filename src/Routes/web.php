<?php

use Illuminate\Support\Facades\Route;
use OpenApi\Builder;

Route::prefix('swagger-ui')->group(function () {
    // 显示swagger UI界面（扫描 Api/Domains/Modules 三种模块布局）
    Route::get('/', function () {
        $apis = [];
        foreach (\glob(app_path('Api/*'), GLOB_ONLYDIR) ?: [] as $path) {
            $apis[\basename($path)] = '/swagger-ui/openapi/'.\basename($path).'.json';
        }

        return view('__foundation__::swagger-ui', ['apis' => $apis]);
    });

    // 生成api文档
    Route::get('openapi/{api}.json', function (string $api) {
        $apiPath = app_path('Api/'.$api);
        if (! \is_dir($apiPath)) {
            throw new Exception('Api模块不存在');
        }

        $dirs = [$apiPath, app_path('Http/Responses')];
        if (strtolower($api) === 'admin') {
            array_push($dirs, app_path('Domains'));
        }

        $openapi = (new Builder)->setSources($dirs)->build();

        return response()->json(json_decode($openapi->toJson(), true));
    });
});

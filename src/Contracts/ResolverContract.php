<?php

declare(strict_types=1);

namespace Juling\DevTools\Contracts;

use Juling\DevTools\Support\DevConfig;

interface ResolverContract
{
    public function entity(DevConfig $devConfig, array $data): void;

    public function model(DevConfig $devConfig, array $data): void;

    public function repository(DevConfig $devConfig, array $data): void;

    public function service(DevConfig $devConfig, array $data): void;

    public function enums(DevConfig $devConfig, array $data): void;

    public function controller(DevConfig $devConfig, array $data): void;

    public function view(DevConfig $devConfig, array $data): void;

    public function route(DevConfig $devConfig, array $data): void;

    public function moduleRoute(DevConfig $devConfig, array $data): void;

    public function dict(DevConfig $devConfig, array $data): void;

    public function mapper(DevConfig $devConfig, array $data): void;

    public function typescript(DevConfig $devConfig, array $data): void;

    public function client(DevConfig $devConfig, array $data): void;

    public function getFieldType(string $fieldType): string;
}

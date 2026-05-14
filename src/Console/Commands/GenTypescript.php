<?php

declare(strict_types=1);

namespace Juling\DevTools\Console\Commands;

use Illuminate\Console\Command;
use Juling\DevTools\Support\SchemaTrait;

class GenTypescript extends Command
{
    use SchemaTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gen:typescript';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate typescript module';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->resolve('typescript');
    }
}

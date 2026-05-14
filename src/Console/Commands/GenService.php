<?php

declare(strict_types=1);

namespace Juling\DevTools\Console\Commands;

use Illuminate\Console\Command;
use Juling\DevTools\Support\SchemaTrait;

class GenService extends Command
{
    use SchemaTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gen:service {--prefix=} {--table=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate service classes';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $tables = $this->getTables($this->option('prefix'), $this->option('table'));
        foreach ($tables as $table) {
            $this->resolve('service', $table);
        }
    }
}

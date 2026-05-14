<?php

declare(strict_types=1);

namespace Juling\DevTools\Console\Commands;

use Illuminate\Console\Command;
use Juling\DevTools\Support\SchemaTrait;

class GenEnums extends Command
{
    use SchemaTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gen:enums {--prefix=} {--table=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate enum classes';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $tables = $this->getTables($this->option('prefix'), $this->option('table'));
        foreach ($tables as $table) {
            $this->resolve('enums', $table);
        }
    }
}

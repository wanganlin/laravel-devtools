<?php

declare(strict_types=1);

namespace Juling\DevTools\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Juling\DevTools\Support\DevConfig;

class InitCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gen:init {--module= : The module subdirectory to initialize} {--force : Force deletion without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'DevTools init';

    public function handle(): void
    {
        $module = (string) $this->option('module');
        if (empty($module)) {
            $this->error('Operation aborted: Initializing without specifying --module is forbidden to prevent accidental deletion of project root.');

            return;
        }

        $devConfig = new DevConfig;
        $dist = $devConfig->getDist($module);

        if ($dist === base_path() || ! str_starts_with($dist, base_path())) {
            $this->error('Security violation: Target path cannot be root directory.');

            return;
        }

        if (! $this->option('force') && ! $this->confirm("Are you sure you want to clean directory [{$dist}]?")) {
            $this->info('Cancelled.');

            return;
        }

        $fs = new Filesystem;
        if ($fs->exists($dist)) {
            $fs->deleteDirectories($dist);
        }

        $fs->ensureDirectoryExists($dist);
        $this->info("Initialized directory: {$dist}");
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class OptimizeAll extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'optimize:all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run all optimization commands in sequence';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $commands = [
            'config:clear',
            'config:cache',
            'cache:clear',
            'route:clear',
            'route:cache',
            'view:clear',
            'view:cache',
            'optimize',
        ];

        $this->info('Running optimization commands...');
        
        foreach ($commands as $command) {
            $this->call($command);
            $this->info("Command '$command' executed successfully.");
        }

        $this->info('All optimization commands executed successfully!');
    }
}

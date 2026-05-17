<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SmokeLocked extends Command
{
    protected $signature = 'smoke:locked';
    protected $description = 'Run smoke tests for all locked features';

    public function handle(): int
    {
        $this->info('Locked Feature Smoke Tests');
        $this->info('=========================');
        $this->newLine();

        $this->line('No features locked yet. Run manual tests first.');
        $this->line('When F1 is locked, this will run smoke:f1.');

        return self::SUCCESS;
    }
}

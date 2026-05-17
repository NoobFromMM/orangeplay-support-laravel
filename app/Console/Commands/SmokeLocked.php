<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SmokeLocked extends Command
{
    protected $signature = 'smoke:locked';
    protected $description = 'Run smoke tests for all locked features';

    public function handle(): int
    {
        $this->info('Locked Feature Smoke Tests');
        $this->info('=========================');

        $failed = false;

        if ($this->call('smoke:f1') !== self::SUCCESS) {
            $failed = true;
        }

        if ($this->call('smoke:f2') !== self::SUCCESS) {
            $failed = true;
        }

        if ($failed) {
            $this->newLine();
            $this->error('One or more locked feature smoke tests FAILED.');
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('All locked feature smoke tests PASSED.');
        return self::SUCCESS;
    }
}

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

        $failed = false;

        $commands = [
            'smoke:f1', 'smoke:f2', 'smoke:f3',
            'smoke:webhook-events', 'smoke:telegram-image', 'smoke:image-admin-reply',
            'smoke:payment-foundation', 'smoke:payment-screenshot', 'smoke:payment-webhook',
            'smoke:payment-email-attach', 'smoke:payment-resolution',
        ];

        foreach ($commands as $command) {
            if (Artisan::call($command) !== self::SUCCESS) {
                $failed = true;
            }
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

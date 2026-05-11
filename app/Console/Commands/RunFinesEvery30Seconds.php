<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RunFinesEvery30Seconds extends Command
{
    protected $signature = 'fines:loop';
    protected $description = 'Run fines:apply every 30 seconds continuously';

    public function handle()
    {
        $this->info("Starting fines:apply loop...");

        while (true) {

            $this->info("Running fines:apply at " . now());
            \Artisan::call('fines:apply');

            sleep(30); // wait for 30 seconds
        }
    }
}

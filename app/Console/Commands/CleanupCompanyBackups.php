<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CleanupCompanyBackups extends Command
{
    protected $signature = 'backups:cleanup';
    protected $description = 'Delete company database backups older than 10 minutes';

    public function handle()
    {
        $backupFolder = storage_path('app/company_backups');

        if (!file_exists($backupFolder)) {
            $this->info('No backup folder found.');
            return;
        }

        foreach (File::files($backupFolder) as $file) {
            if ($file->getCTime() < now()->subMinutes(10)->timestamp) {
                File::delete($file);
                $this->info("Deleted old backup: " . $file->getFilename());
            }
           
        }

        $this->info('Backup cleanup complete.');
    }
}

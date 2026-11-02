<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class CreateBackup extends Command
{
    protected $signature = 'nansan:backup {--verify-id= : Test an existing backup using an isolated scratch restore}';

    protected $description = 'Create and verify an encrypted private backup; never restore over a live database';

    public function handle(BackupService $service): int
    {
        try {
            if ($id = $this->option('verify-id')) {
                $service->verifyRestore((int) $id);
                $this->info('Isolated restore verified.');
            } else {
                $id = $service->create();
                $this->info('Encrypted backup #'.$id.' created and checksum-verified; restore remains pending.');
            }

            return self::SUCCESS;
        } catch (\Throwable) {
            $this->error('Backup operation failed. Check configuration, available space, key, and isolated restore requirements. No success is claimed.');

            return self::FAILURE;
        }
    }
}

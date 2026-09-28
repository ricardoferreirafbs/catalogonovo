<?php

namespace App\Console\Commands;

use App\Services\CatalogBackupManager;
use Illuminate\Console\Command;
use Throwable;

class PruneCatalogBackups extends Command
{
    protected $signature = 'backup:prune';

    protected $description = 'Remove backups vencidos preservando o número mínimo de cópias';

    public function handle(CatalogBackupManager $manager): int
    {
        try {
            $deleted = $manager->prune();
            $this->info("{$deleted} backup(s) vencido(s) removido(s).");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Falha na retenção de backups: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}

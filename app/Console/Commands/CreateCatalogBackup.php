<?php

namespace App\Console\Commands;

use App\Services\CatalogBackupManager;
use Illuminate\Console\Command;
use Throwable;

class CreateCatalogBackup extends Command
{
    protected $signature = 'backup:create';

    protected $description = 'Cria um backup criptografado do banco e das imagens dos clientes';

    public function handle(CatalogBackupManager $manager): int
    {
        try {
            $result = $manager->create();
            $this->info('Backup criado e autenticado com sucesso.');
            $this->line('Arquivo: '.$result['path']);
            $this->line("Banco: {$result['rows']} registro(s) · Uploads: {$result['files']} arquivo(s)");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Falha ao criar o backup: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Services\CatalogBackupManager;
use Illuminate\Console\Command;
use Throwable;

class CreateCatalogBackup extends Command
{
    protected $signature = 'backup:create
        {--scheduled : Identifica uma execução iniciada pelo agendador da aplicação}';

    protected $description = 'Cria um backup criptografado do banco e das imagens dos clientes';

    public function handle(CatalogBackupManager $manager): int
    {
        try {
            $result = $manager->create($this->option('scheduled') ? 'scheduled' : 'manual');
            $this->info('Backup criado e autenticado com sucesso.');
            $this->line('Arquivo: '.$result['path']);
            $this->line('Origem: '.($result['source'] === 'scheduled' ? 'agendador' : 'manual'));
            $this->line("Banco: {$result['rows']} registro(s) · Uploads: {$result['files']} arquivo(s)");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Falha ao criar o backup: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}

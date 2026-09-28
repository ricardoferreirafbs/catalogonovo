<?php

namespace App\Console\Commands;

use App\Services\CatalogBackupManager;
use Illuminate\Console\Command;
use Throwable;

class VerifyCatalogBackup extends Command
{
    protected $signature = 'backup:verify {file? : Caminho relativo exibido por backup:list}';

    protected $description = 'Autentica e verifica a integridade de um backup sem restaurá-lo';

    public function handle(CatalogBackupManager $manager): int
    {
        $file = $this->argument('file') ?: ($manager->files()[0] ?? null);

        if (! $file) {
            $this->error('Nenhum backup foi encontrado.');

            return self::FAILURE;
        }

        try {
            $result = $manager->verify($file);
            $metadata = $result['metadata'];
            $footer = $result['footer'];
            $this->info('Integridade e autenticação confirmadas.');
            $this->line('Arquivo: '.$file);
            $this->line('Criado em UTC: '.($metadata['created_at'] ?? 'não informado'));
            $this->line('Banco: '.($footer['rows'] ?? 0).' registro(s) · Uploads: '.($footer['files'] ?? 0).' arquivo(s)');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Backup inválido: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}

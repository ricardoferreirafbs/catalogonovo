<?php

namespace App\Console\Commands;

use App\Services\CatalogBackupManager;
use Illuminate\Console\Command;
use Throwable;

class RestoreCatalogBackup extends Command
{
    protected $signature = 'backup:restore
        {file : Caminho relativo exibido por backup:list}
        {--confirm= : Deve ser RESTORE-INTO-EMPTY-DATABASE}
        {--force : Autoriza a execução quando APP_ENV=production}';

    protected $description = 'Restaura banco e uploads somente em uma instalação vazia';

    public function handle(CatalogBackupManager $manager): int
    {
        if ($this->option('confirm') !== 'RESTORE-INTO-EMPTY-DATABASE') {
            $this->error('Confirmação ausente. Use --confirm=RESTORE-INTO-EMPTY-DATABASE somente no ambiente vazio de destino.');

            return self::FAILURE;
        }

        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Em produção, a restauração exige também --force e deve ocorrer com o site em manutenção.');

            return self::FAILURE;
        }

        try {
            $result = $manager->restore((string) $this->argument('file'));
            $this->info('Backup restaurado e validado com sucesso.');
            $this->line("Banco: {$result['rows']} registro(s) · Uploads: {$result['files']} arquivo(s)");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('A restauração foi interrompida: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Services\CatalogBackupManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ListCatalogBackups extends Command
{
    protected $signature = 'backup:list';

    protected $description = 'Lista os backups criptografados disponíveis no disco configurado';

    public function handle(CatalogBackupManager $manager): int
    {
        $files = $manager->files();

        if ($files === []) {
            $this->warn('Nenhum backup disponível.');

            return self::SUCCESS;
        }

        $disk = Storage::disk((string) config('backup.disk', 'local'));
        $this->table(['Arquivo', 'Tamanho', 'Modificado em UTC'], collect($files)->map(fn (string $file) => [
            $file,
            number_format($disk->size($file) / 1024 / 1024, 2, ',', '.').' MB',
            gmdate('Y-m-d H:i:s', $disk->lastModified($file)),
        ]));

        return self::SUCCESS;
    }
}

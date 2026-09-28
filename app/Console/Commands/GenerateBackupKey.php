<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateBackupKey extends Command
{
    protected $signature = 'backup:key-generate';

    protected $description = 'Gera uma chave exclusiva para criptografar os backups da Catalog';

    public function handle(): int
    {
        $this->warn('Guarde esta chave fora da hospedagem. Sem ela, o backup não poderá ser restaurado.');
        $this->line('BACKUP_ENCRYPTION_KEY="base64:'.base64_encode(random_bytes(32)).'"');

        return self::SUCCESS;
    }
}

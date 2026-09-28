<?php

namespace App\Console\Commands;

use App\Services\EncryptedBackupArchive;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DiagnoseCatalogBackup extends Command
{
    protected $signature = 'backup:diagnose';

    protected $description = 'Verifica os requisitos do backup criptografado sem exibir segredos';

    public function handle(): int
    {
        $failures = 0;
        $failures += $this->check((bool) config('backup.enabled'), 'Agendamento habilitado', 'defina BACKUP_ENABLED=true após homologar');
        $failures += $this->check(extension_loaded('openssl'), 'Extensão PHP OpenSSL');
        $failures += $this->check(
            extension_loaded('openssl') && in_array('aes-256-gcm', openssl_get_cipher_methods(), true),
            'Criptografia autenticada AES-256-GCM'
        );
        $failures += $this->check(filled(config('app.key')), 'APP_KEY configurada');

        try {
            new EncryptedBackupArchive;
            $failures += $this->check(true, 'Chave exclusiva de backup válida');
        } catch (Throwable) {
            $failures += $this->check(false, 'Chave exclusiva de backup válida', 'gere com php artisan backup:key-generate');
        }

        $probe = trim((string) config('backup.directory', 'backups'), '/').'/.write-test-'.bin2hex(random_bytes(4));

        try {
            $disk = Storage::disk((string) config('backup.disk', 'local'));
            $written = $disk->put($probe, 'catalog-backup-write-test');
            $readBack = $written ? $disk->get($probe) : null;
            $deleted = $written && $disk->delete($probe);
            $failures += $this->check($written && $readBack === 'catalog-backup-write-test' && $deleted, 'Disco de backup permite escrita, leitura e exclusão');
        } catch (Throwable) {
            $failures += $this->check(false, 'Disco de backup permite escrita, leitura e exclusão');
        }

        $failures += $this->check(Storage::disk('uploads')->exists('') || is_dir(public_path('uploads')), 'Pasta de uploads disponível');

        if ($failures > 0) {
            $this->newLine();
            $this->error("Diagnóstico concluído com {$failures} requisito(s) pendente(s).");

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Backup pronto para homologação. Nenhuma chave foi exibida.');

        return self::SUCCESS;
    }

    private function check(bool $passed, string $label, ?string $detail = null): int
    {
        $status = $passed ? '<fg=green>OK</>' : '<fg=red>FALHA</>';
        $suffix = ! $passed && $detail ? " — {$detail}" : '';
        $this->line("[{$status}] {$label}{$suffix}");

        return $passed ? 0 : 1;
    }
}

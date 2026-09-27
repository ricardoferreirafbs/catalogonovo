<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DiagnoseWebPush extends Command
{
    protected $signature = 'push:diagnose';

    protected $description = 'Verifica os requisitos do Web Push sem exibir chaves ou assinaturas';

    public function handle(): int
    {
        $failures = 0;

        $failures += $this->check(PHP_VERSION_ID >= 80200, 'PHP 8.2 ou superior', PHP_VERSION);

        foreach (['curl', 'mbstring', 'openssl'] as $extension) {
            $failures += $this->check(extension_loaded($extension), "Extensão PHP {$extension}");
        }

        $ecSupported = false;

        if (extension_loaded('openssl') && function_exists('openssl_pkey_new')) {
            while (openssl_error_string() !== false) {
                // Descarta erros anteriores para que o teste não exponha mensagens sem relação.
            }

            $key = @openssl_pkey_new([
                'private_key_type' => OPENSSL_KEYTYPE_EC,
                'curve_name' => 'prime256v1',
            ]);
            $ecSupported = $key !== false;
        }

        $failures += $this->check(
            $ecSupported,
            'Geração de chave EC prime256v1',
            $ecSupported ? null : 'confira o suporte EC e a variável OPENSSL_CONF do servidor'
        );

        $subject = (string) config('webpush.subject');
        $validSubject = str_starts_with($subject, 'mailto:') || filter_var($subject, FILTER_VALIDATE_URL) !== false;
        $failures += $this->check($validSubject, 'Identificação VAPID', 'use mailto:contato@dominio ou uma URL HTTPS');

        $publicConfigured = filled(config('webpush.public_key'));
        $privateConfigured = filled(config('webpush.private_key'));
        $failures += $this->check(
            $publicConfigured && $privateConfigured,
            'Par de chaves VAPID configurado',
            'as duas variáveis devem existir no .env'
        );

        $https = str_starts_with(strtolower((string) config('app.url')), 'https://');
        $failures += $this->check($https, 'APP_URL utiliza HTTPS', (string) config('app.url'));

        if ($failures > 0) {
            $this->newLine();
            $this->error("Diagnóstico concluído com {$failures} requisito(s) pendente(s).");

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Web Push pronto. Nenhuma chave ou assinatura foi exibida.');

        return self::SUCCESS;
    }

    private function check(bool $passed, string $label, ?string $detail = null): int
    {
        $status = $passed ? '<fg=green>OK</>' : '<fg=red>FALHA</>';
        $suffix = $detail ? " — {$detail}" : '';

        $this->line("[{$status}] {$label}{$suffix}");

        return $passed ? 0 : 1;
    }
}

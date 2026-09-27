<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;
use Throwable;

class GenerateVapidKeys extends Command
{
    protected $signature = 'push:vapid-generate';

    protected $description = 'Gera um novo par VAPID para Web Push sem gravá-lo em arquivos';

    public function handle(): int
    {
        try {
            $keys = VAPID::createVapidKeys();
        } catch (Throwable $exception) {
            report($exception);

            $this->error('Não foi possível gerar as chaves VAPID neste servidor.');
            $this->line('Verifique a extensão OpenSSL, o suporte a chaves EC prime256v1 e a configuração OPENSSL_CONF.');
            $this->line('Execute "php artisan push:diagnose" para identificar o requisito ausente.');

            return self::FAILURE;
        }

        $this->warn('Guarde estas chaves no .env. A chave privada não poderá ser recuperada depois.');
        $this->line('WEBPUSH_VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('WEBPUSH_VAPID_PRIVATE_KEY='.$keys['privateKey']);

        return self::SUCCESS;
    }
}

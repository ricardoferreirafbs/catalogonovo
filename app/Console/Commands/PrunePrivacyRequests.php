<?php

namespace App\Console\Commands;

use App\Models\PrivacyRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class PrunePrivacyRequests extends Command
{
    protected $signature = 'privacy:prune';

    protected $description = 'Remove solicitações de privacidade após o prazo de retenção';

    public function handle(): int
    {
        if (! Schema::hasTable('privacy_requests')) {
            $this->warn('A tabela de solicitações de privacidade ainda não existe. Execute as migrations.');

            return self::SUCCESS;
        }

        $deleted = PrivacyRequest::where('retention_until', '<', now())->delete();
        $this->info("{$deleted} solicitação(ões) de privacidade removida(s) conforme a retenção.");

        return self::SUCCESS;
    }
}

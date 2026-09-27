<?php

namespace App\Console\Commands;

use App\Models\Communication;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class PruneCommunications extends Command
{
    protected $signature = 'communications:prune';

    protected $description = 'Exclui comunicações e mensagens após o prazo de retenção';

    public function handle(): int
    {
        if (! Schema::hasTable('communications')) {
            return self::SUCCESS;
        }

        $deleted = Communication::query()->whereNotNull('expires_at')->where('expires_at', '<', now())->delete();
        $this->info("{$deleted} comunicação(ões) removida(s) conforme a retenção.");

        return self::SUCCESS;
    }
}

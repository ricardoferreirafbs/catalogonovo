<?php

namespace App\Console\Commands;

use App\Models\ErrorOccurrence;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class PruneErrorOccurrences extends Command
{
    protected $signature = 'occurrences:prune';

    protected $description = 'Remove ocorrências cujo prazo individual de retenção terminou';

    public function handle(): int
    {
        if (! Schema::hasTable('error_occurrences')) {
            $this->warn('A tabela de ocorrências ainda não existe. Execute as migrations.');

            return self::SUCCESS;
        }

        $deleted = ErrorOccurrence::where('retention_until', '<', now())->delete();

        $this->info("{$deleted} ocorrência(s) removida(s) conforme a política de retenção.");

        return self::SUCCESS;
    }
}

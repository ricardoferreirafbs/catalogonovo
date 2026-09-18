<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

class PruneAuditLogs extends Command
{
    protected $signature = 'audit:prune {--days= : Dias de retenção; usa AUDIT_RETENTION_DAYS quando omitido}';

    protected $description = 'Remove registros de auditoria mais antigos que o prazo de retenção';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('security.audit_retention_days', 180));

        if ($days < 30) {
            $this->error('O prazo mínimo de retenção configurável é de 30 dias.');

            return self::FAILURE;
        }

        $deleted = AuditLog::where('created_at', '<', now()->subDays($days))->delete();
        $this->info("{$deleted} registro(s) de auditoria removido(s). Retenção: {$days} dias.");

        return self::SUCCESS;
    }
}

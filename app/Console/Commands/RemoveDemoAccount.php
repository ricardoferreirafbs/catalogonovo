<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class RemoveDemoAccount extends Command
{
    protected $signature = 'security:remove-demo-account {--force : Remove sem pedir confirmação}';

    protected $description = 'Remove a conta de demonstração conhecida sem excluir a empresa ou seu catálogo';

    public function handle(): int
    {
        $email = 'admin@catalogo.test';
        $count = User::where('email', $email)->count();

        if ($count === 0) {
            $this->info('A conta de demonstração não existe. Nenhuma alteração foi necessária.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Remover definitivamente {$count} conta(s) com o e-mail {$email}?")) {
            $this->warn('Operação cancelada.');

            return self::FAILURE;
        }

        User::where('email', $email)->delete();
        $this->info('Conta de demonstração removida. A empresa e o catálogo foram preservados.');

        return self::SUCCESS;
    }
}

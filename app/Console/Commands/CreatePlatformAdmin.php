<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreatePlatformAdmin extends Command
{
    protected $signature = 'platform:admin {email} {--name=Super Administrador}';

    protected $description = 'Cria o usuário superadministrador da plataforma';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));

        if (User::where('email', $email)->exists()) {
            $this->error("O e-mail {$email} já está em uso.");

            return self::FAILURE;
        }

        $password = $this->secret('Defina a senha inicial (mínimo 12 caracteres)');

        if (! is_string($password) || strlen($password) < 12) {
            $this->error('A senha deve ter pelo menos 12 caracteres.');

            return self::FAILURE;
        }

        User::create([
            'tenant_id' => null,
            'name' => $this->option('name'),
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'superadmin',
        ]);

        $this->info("Superadministrador {$email} criado. Acesse /plataforma após entrar.");

        return self::SUCCESS;
    }
}

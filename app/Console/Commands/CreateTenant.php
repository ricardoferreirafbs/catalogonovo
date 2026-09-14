<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateTenant extends Command
{
    protected $signature = 'tenant:create {name} {email} {--slug=} {--domain=} {--plan=starter}';

    protected $description = 'Cria uma empresa e o primeiro usuário administrador';

    public function handle(): int
    {
        $slug = Str::slug($this->option('slug') ?: $this->argument('name'));
        $email = strtolower($this->argument('email'));

        if (Tenant::where('slug', $slug)->exists() || User::where('email', $email)->exists()) {
            $this->error('Já existe uma empresa com esse slug ou um usuário com esse e-mail.');

            return self::FAILURE;
        }

        $password = $this->secret('Defina a senha inicial (mínimo 8 caracteres)');
        if (! is_string($password) || strlen($password) < 8) {
            $this->error('A senha deve ter pelo menos 8 caracteres.');

            return self::FAILURE;
        }

        $tenant = DB::transaction(function () use ($slug, $email, $password) {
            $tenant = Tenant::create([
                'name' => $this->argument('name'),
                'slug' => $slug,
                'custom_domain' => $this->option('domain') ?: null,
                'plan' => $this->option('plan'),
                'theme' => [
                    'primary' => '#173f35', 'accent' => '#e48a4a', 'surface' => '#f4f6f3',
                    'hero_title' => 'Conheça nossa coleção.',
                    'hero_text' => 'Produtos selecionados e atendimento próximo.',
                    'font_style' => 'modern', 'card_style' => 'soft',
                ],
            ]);
            User::create([
                'tenant_id' => $tenant->id,
                'name' => 'Administrador',
                'email' => $email,
                'password' => Hash::make($password),
                'role' => 'owner',
            ]);

            return $tenant;
        });

        $this->info("Empresa {$tenant->name} criada com o identificador {$tenant->slug}.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
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
        $domain = $this->option('domain');
        $domain = is_string($domain) && trim($domain) !== '' ? strtolower(trim($domain)) : null;

        if (Tenant::where('slug', $slug)->exists()) {
            $this->error("Já existe uma empresa com o identificador {$slug}. Use outro valor em --slug.");

            return self::FAILURE;
        }

        if ($domain && ($existingTenant = Tenant::where('custom_domain', $domain)->first())) {
            $this->error("O domínio {$domain} já está vinculado à empresa {$existingTenant->name} ({$existingTenant->slug}).");

            return self::FAILURE;
        }

        if (User::where('email', $email)->exists()) {
            $this->error("Já existe um usuário com o e-mail {$email}.");

            return self::FAILURE;
        }

        $password = $this->secret('Defina a senha inicial (mínimo 8 caracteres)');
        if (! is_string($password) || strlen($password) < 8) {
            $this->error('A senha deve ter pelo menos 8 caracteres.');

            return self::FAILURE;
        }

        try {
            $tenant = DB::transaction(function () use ($slug, $email, $password, $domain) {
                $tenant = Tenant::create([
                    'name' => $this->argument('name'),
                    'slug' => $slug,
                    'custom_domain' => $domain,
                    'plan' => $this->option('plan'),
                    'theme' => Tenant::defaultTheme(),
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
        } catch (UniqueConstraintViolationException) {
            $this->error('Não foi possível criar a empresa porque o slug, domínio ou e-mail já está em uso.');

            return self::FAILURE;
        }

        $this->info("Empresa {$tenant->name} criada com o identificador {$tenant->slug}.");

        return self::SUCCESS;
    }
}

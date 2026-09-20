<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Support\ErrorReference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ErrorReferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_forbidden_page_has_a_faq_code_and_unique_protocol(): void
    {
        $tenant = Tenant::create(['name' => 'Cliente', 'slug' => 'cliente']);
        $viewer = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'viewer',
        ]);

        $response = $this->actingAs($viewer)
            ->withSession(['mfa_verified_user_id' => $viewer->id])
            ->get(route('admin.theme.edit'));

        $response->assertForbidden()
            ->assertSee('CAT-403-ACESSO')
            ->assertSee('Protocolo desta ocorrência')
            ->assertDontSee('EnsurePermission');
        $response->assertHeader('X-Error-Code', 'CAT-403-ACESSO');
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{10}$/', (string) $response->headers->get('X-Error-Protocol'));
    }

    public function test_each_error_occurrence_receives_a_different_protocol(): void
    {
        $first = $this->get('/pagina-que-nao-existe');
        $second = $this->get('/outra-pagina-que-nao-existe');

        $first->assertNotFound()->assertSee('CAT-404-RECURSO');
        $second->assertNotFound()->assertSee('CAT-404-RECURSO');
        $this->assertNotSame(
            $first->headers->get('X-Error-Protocol'),
            $second->headers->get('X-Error-Protocol'),
        );
    }

    public function test_internal_error_does_not_expose_exception_details_in_production_mode(): void
    {
        config(['app.debug' => false]);
        Route::get('/erro-controlado-de-teste', fn () => throw new RuntimeException('SEGREDO_INTERNO_NAO_EXIBIR'));

        $response = $this->get('/erro-controlado-de-teste');

        $response->assertInternalServerError()
            ->assertSee('CAT-500-INTERNO')
            ->assertDontSee('SEGREDO_INTERNO_NAO_EXIBIR')
            ->assertHeader('X-Error-Code', 'CAT-500-INTERNO');
    }

    public function test_json_errors_keep_the_api_response_format(): void
    {
        Route::get('/erro-json-de-teste', fn () => abort(403));

        $response = $this->getJson('/erro-json-de-teste');

        $response->assertForbidden()
            ->assertJsonStructure(['message'])
            ->assertHeaderMissing('X-Error-Code');
    }

    public function test_protocol_is_alphanumeric_and_faq_references_are_stable(): void
    {
        $this->assertSame('CAT-419-SESSAO', ErrorReference::forStatus(419)['code']);
        $this->assertSame('CAT-429-LIMITE', ErrorReference::forStatus(429)['code']);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{10}$/', ErrorReference::protocol());
    }

    public function test_expired_session_has_clear_recovery_instructions(): void
    {
        Route::get('/sessao-expirada-de-teste', fn () => abort(419));

        $this->get('/sessao-expirada-de-teste')
            ->assertStatus(419)
            ->assertSee('CAT-419-SESSAO')
            ->assertSee('Atualize a página')
            ->assertHeader('X-Error-Code', 'CAT-419-SESSAO');
    }

    public function test_error_help_center_is_public_and_lists_all_references(): void
    {
        $response = $this->get(route('help.errors'));

        $response->assertOk()
            ->assertSee('Central de ajuda')
            ->assertSee('CAT-401-AUTENTICACAO')
            ->assertSee('CAT-403-ACESSO')
            ->assertSee('CAT-404-RECURSO')
            ->assertSee('CAT-419-SESSAO')
            ->assertSee('CAT-429-LIMITE')
            ->assertSee('CAT-500-INTERNO')
            ->assertSee('CAT-503-INDISPONIVEL')
            ->assertSee('nunca deve pedir sua senha', escape: false);
    }

    public function test_error_page_links_to_the_matching_faq_reference(): void
    {
        $response = $this->get('/pagina-inexistente-para-ajuda');

        $response->assertNotFound()
            ->assertSee(route('help.errors', ['codigo' => 'CAT-404-RECURSO']), escape: false)
            ->assertSee('Consultar este erro na FAQ');
    }
}

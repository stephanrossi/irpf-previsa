<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Declaration;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientComplexityFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_clients_by_any_declaration_complexity(): void
    {
        $clienteA = Client::create(['nome' => 'Cliente A', 'cpf' => '11111111111']);
        $clienteB = Client::create(['nome' => 'Cliente B', 'cpf' => '22222222222']);
        $clienteC = Client::create(['nome' => 'Cliente C', 'cpf' => '33333333333']);

        $this->createDeclaration($clienteA->id, 2024, 'alta');
        $this->createDeclaration($clienteA->id, 2023, 'media');
        $this->createDeclaration($clienteA->id, 2022, 'baixa');

        $this->createDeclaration($clienteB->id, 2024, 'media');
        $this->createDeclaration($clienteB->id, 2023, 'baixa');

        $this->createDeclaration($clienteC->id, 2024, 'baixa');

        $alta = $this->get(route('clients.index', ['complexity' => 'alta']));
        $alta->assertOk();
        $alta->assertSee('Cliente A');
        $alta->assertDontSee('Cliente B');
        $alta->assertDontSee('Cliente C');

        $media = $this->get(route('clients.index', ['complexity' => 'media']));
        $media->assertOk();
        $media->assertSee('Cliente A');
        $media->assertSee('Cliente B');
        $media->assertDontSee('Cliente C');

        $baixa = $this->get(route('clients.index', ['complexity' => 'baixa']));
        $baixa->assertOk();
        $baixa->assertSee('Cliente A');
        $baixa->assertSee('Cliente B');
        $baixa->assertSee('Cliente C');
    }

    public function test_filters_clients_by_any_declaration_ano_base(): void
    {
        $clienteA = Client::create(['nome' => 'Cliente A', 'cpf' => '11111111111']);
        $clienteB = Client::create(['nome' => 'Cliente B', 'cpf' => '22222222222']);
        $clienteC = Client::create(['nome' => 'Cliente C', 'cpf' => '33333333333']);

        $this->createDeclaration($clienteA->id, 2024, 'alta');
        $this->createDeclaration($clienteA->id, 2023, 'media');
        $this->createDeclaration($clienteB->id, 2023, 'baixa');
        $this->createDeclaration($clienteC->id, 2022, 'baixa');

        $ano2024 = $this->get(route('clients.index', ['ano_base' => 2024]));
        $ano2024->assertOk();
        $ano2024->assertSee('Cliente A');
        $ano2024->assertDontSee('Cliente B');
        $ano2024->assertDontSee('Cliente C');

        $ano2023 = $this->get(route('clients.index', ['ano_base' => 2023]));
        $ano2023->assertOk();
        $ano2023->assertSee('Cliente A');
        $ano2023->assertSee('Cliente B');
        $ano2023->assertDontSee('Cliente C');

        $ano2022 = $this->get(route('clients.index', ['ano_base' => 2022]));
        $ano2022->assertOk();
        $ano2022->assertDontSee('Cliente A');
        $ano2022->assertDontSee('Cliente B');
        $ano2022->assertSee('Cliente C');
    }

    public function test_risk_status_and_filter_consider_only_latest_imported_declaration(): void
    {
        $clienteA = Client::create(['nome' => 'Cliente A', 'cpf' => '11111111111']);
        $clienteB = Client::create(['nome' => 'Cliente B', 'cpf' => '22222222222']);

        // Cliente A: antiga em risco, ultima importada sem risco => status OK.
        $this->createDeclaration($clienteA->id, 2022, 'alta', [
            'imported_at' => Carbon::parse('2026-02-20 10:00:00'),
            'total_bens_reais' => 100,
            'rend_trib_pj' => 0,
        ]);
        $this->createDeclaration($clienteA->id, 2024, 'media', [
            'imported_at' => Carbon::parse('2026-02-25 10:00:00'),
            'total_bens_reais' => 100,
            'rend_trib_pj' => 100,
        ]);

        // Cliente B: ultima importada em risco => status Em risco.
        $this->createDeclaration($clienteB->id, 2023, 'baixa', [
            'imported_at' => Carbon::parse('2026-02-21 10:00:00'),
            'total_bens_reais' => 100,
            'rend_trib_pj' => 100,
        ]);
        $this->createDeclaration($clienteB->id, 2024, 'alta', [
            'imported_at' => Carbon::parse('2026-02-26 10:00:00'),
            'total_bens_reais' => 100,
            'rend_trib_pj' => 0,
        ]);

        $response = $this->get(route('clients.index'));
        $response->assertOk();
        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/Cliente A.*?OK/s', $html);
        $this->assertMatchesRegularExpression('/Cliente B.*?Em risco/s', $html);

        $riskOnly = $this->get(route('clients.index', ['risk_only' => 1]));
        $riskOnly->assertOk();
        $riskOnly->assertSee('Cliente B');
        $riskOnly->assertDontSee('Cliente A');
    }

    private function createDeclaration(int $clientId, int $anoBase, string $complexity, array $overrides = []): void
    {
        $base = [
            'client_id' => $clientId,
            'exercicio' => $anoBase + 1,
            'ano_base' => $anoBase,
            'tipo' => 'completa',
            'total_rend_tributaveis' => 0,
            'total_bens_imoveis' => 0,
            'total_dividas_onus' => 0,
            'total_bens_adquiridos_ano' => 0,
            'source_file_path' => "declarations/{$clientId}/{$anoBase}.dec",
            'imported_at' => now(),
            'complexity_score' => match ($complexity) {
                'alta' => 30,
                'media' => 10,
                default => 1,
            },
            'complexity_level' => $complexity,
            'complexity_breakdown' => [],
        ];

        Declaration::create(array_merge($base, $overrides));
    }
}

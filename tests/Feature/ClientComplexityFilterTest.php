<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Declaration;
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

    private function createDeclaration(int $clientId, int $anoBase, string $complexity): void
    {
        Declaration::create([
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
        ]);
    }
}

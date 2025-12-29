<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Declaration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeclarationReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_renders_report_with_status_and_values(): void
    {
        $client = Client::create([
            'nome' => 'Cliente Teste',
            'cpf' => '12345678901',
        ]);

        $declaration = Declaration::create([
            'client_id' => $client->id,
            'exercicio' => 2024,
            'ano_base' => 2023,
            'tipo' => 'completa',
            'total_rend_tributaveis' => 100.00,
            'total_renda_isenta' => 50.00,
            'gastos_declarados_total' => 80.00,
            'total_bens_imoveis' => 0,
            'total_dividas_onus' => 0,
            'total_bens_adquiridos_ano' => 500.00,
            'variacao_patrimonial_descoberto' => 350.00,
            'risco_variacao_patrimonial' => true,
            'source_file_path' => 'declarations/1/test.dec',
            'imported_at' => now(),
        ]);

        $response = $this->get(route('declarations.report', $declaration));

        $response->assertStatus(200);
        $response->assertSee('EM RISCO');
        $response->assertSee('Variação a descoberto');
        $response->assertSee('Renda Tributável');
        $response->assertSee('Gastos Estimados');
        $response->assertSee('Gastos declarados');
    }
}

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
            'rend_trib_pj' => 100.00,
            'rend_trib_pf_exterior' => 0,
            'total_renda_isenta' => 50.00,
            'total_rend_exclusiva' => 0,
            'total_rend_recebidos_acumuladamente' => 0,
            'total_renda_variavel' => 0,
            'total_atividade_rural_resultado_tributavel' => 0,
            'total_pagamentos_efetuados' => 0,
            'total_doacoes_efetuadas' => 0,
            'total_doacoes_partidos_politicos' => 0,
            'total_dividas_onus_reais' => 0,
            'total_bens_reais' => 1000.00,
            'gastos_estimados' => 0,
            'gastos_declarados_total' => 80.00,
            'total_bens_imoveis' => 0,
            'total_dividas_onus' => 0,
            'total_bens_adquiridos_ano' => 500.00,
            'source_file_path' => 'declarations/1/test.dec',
            'imported_at' => now(),
        ]);

        $response = $this->get(route('declarations.report', $declaration));

        $response->assertStatus(200);
        $response->assertSee('EM RISCO - Caixa abaixo de 20% da Evolucao Patrimonial');
        $response->assertSee('Rendimentos tributaveis');
        $response->assertSee('Regra aplicada');
        $response->assertSee('Limite de risco (20%)');
        $response->assertSee('Gastos declarados');
    }
}
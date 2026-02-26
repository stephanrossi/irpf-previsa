<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateEstimatedExpensesRequest;
use App\Models\Declaration;
use App\Services\IrpfInconsistencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class DeclarationController extends Controller
{
    public function updateExpenses(UpdateEstimatedExpensesRequest $request, Declaration $declaration, IrpfInconsistencyService $service): JsonResponse
    {
        $value = $request->validated('gastos_estimados');
        $topIsentos = $declaration->isentos()
            ->orderByDesc('valor')
            ->limit(5)
            ->get(['cod_isento as codigo', 'valor'])
            ->toArray();

        $service->applyToDeclaration($declaration, $value, $topIsentos);
        $declaration->refresh();
        $metrics = $this->currentCaixaRiskMetrics($declaration);

        return response()->json([
            'message' => 'Gastos estimados atualizados.',
            'risco' => $metrics['risco'],
            'status' => $metrics['risco'] ? 'EM RISCO' : 'OK',
            'caixa' => number_format($metrics['caixa_total'], 2, ',', '.'),
            'limite' => number_format($metrics['limite_risco'], 2, ',', '.'),
        ]);
    }

    private function currentCaixaRiskMetrics(Declaration $declaration): array
    {
        $gastosEstimados = $declaration->gastos_estimados !== null ? (float) $declaration->gastos_estimados : 0.0;
        $entradasSaidas = (
            (float) $declaration->rend_trib_pj
            + (float) $declaration->rend_trib_pf_exterior
            + (float) $declaration->total_renda_isenta
            + (float) $declaration->total_rend_exclusiva
            + (float) $declaration->total_rend_recebidos_acumuladamente
            + (float) $declaration->total_renda_variavel
            + (float) $declaration->total_bens_reais
            + (float) $declaration->total_atividade_rural_resultado_tributavel
            - (float) $declaration->total_pagamentos_efetuados
            - (float) $declaration->total_doacoes_efetuadas
            - (float) $declaration->total_doacoes_partidos_politicos
            - (float) $declaration->total_dividas_onus_reais
            - $gastosEstimados
        );

        $evolucaoPatrimonial = (float) $declaration->total_bens_reais;
        $caixaTotal = $entradasSaidas - $evolucaoPatrimonial;
        $limiteRisco = $evolucaoPatrimonial * 0.2;
        $risco = $evolucaoPatrimonial > 0 && $caixaTotal < $limiteRisco;

        return [
            'entradas_saidas' => $entradasSaidas,
            'evolucao_patrimonial' => $evolucaoPatrimonial,
            'caixa_total' => $caixaTotal,
            'limite_risco' => $limiteRisco,
            'risco' => $risco,
        ];
    }

    public function showReport(Declaration $declaration): View
    {
        $declaration->load(['client', 'isentos' => fn ($q) => $q->orderByDesc('valor')]);
        $payload = $declaration->inconsistencia_payload ?? [];
        $riskMetrics = $this->currentCaixaRiskMetrics($declaration);

        return view('declarations.report', [
            'declaration' => $declaration,
            'payload' => $payload,
            'riskMetrics' => $riskMetrics,
            'topIsentos' => $declaration->isentos,
        ]);
    }
}

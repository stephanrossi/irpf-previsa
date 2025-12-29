<?php

namespace App\Services;

use App\DataTransferObjects\IrpfInconsistencyResult;
use App\Models\Declaration;

class IrpfInconsistencyService
{
    public function calculate(Declaration $declaration, ?float $gastosEstimados = null, array $topIsentos = []): IrpfInconsistencyResult
    {
        $gastosEstimadosValor = $gastosEstimados ?? ($declaration->gastos_estimados ?? 0.0);
        $gastosDeclarados = (float) $declaration->gastos_declarados_total;
        $gastosTotalAnalise = $gastosEstimadosValor + $gastosDeclarados;
        $rendaTrib = (float) $declaration->total_rend_tributaveis;
        $rendaIsenta = (float) $declaration->total_renda_isenta;
        $bensAno = (float) $declaration->total_bens_adquiridos_ano;

        $rendaTotal = $rendaTrib + $rendaIsenta;
        $baseDisponivel = $rendaTotal - $gastosTotalAnalise;
        $variacao = $bensAno - $baseDisponivel;
        $risco = $variacao > 0;
        $variacaoPersisted = $risco ? $variacao : 0.0;

        $payload = [
            'renda_tributavel_total' => $rendaTrib,
            'total_renda_isenta' => $rendaIsenta,
            'gastos_estimados' => $gastosEstimadosValor,
            'gastos_declarados_total' => $gastosDeclarados,
            'gastos_declarados_breakdown' => $declaration->gastos_declarados_breakdown,
            'total_bens_adquiridos_ano' => $bensAno,
            'variacao_patrimonial_descoberto' => $variacao,
            'status' => $risco ? 'RISCO' : 'OK',
            'top_isentos' => $topIsentos,
        ];

        return new IrpfInconsistencyResult(
            rendaTributavel: $rendaTrib,
            rendaIsenta: $rendaIsenta,
            gastosEstimados: $gastosEstimadosValor,
            totalBensAdquiridos: $bensAno,
            variacaoDescoberto: $variacaoPersisted,
            risco: $risco,
            payload: $payload,
        );
    }

    public function applyToDeclaration(Declaration $declaration, ?float $gastosEstimados = null, array $topIsentos = []): IrpfInconsistencyResult
    {
        $result = $this->calculate($declaration, $gastosEstimados, $topIsentos);

        $declaration->fill([
            'gastos_estimados' => $gastosEstimados ?? $declaration->gastos_estimados,
            'variacao_patrimonial_descoberto' => $result->variacaoDescoberto,
            'risco_variacao_patrimonial' => $result->risco,
            'inconsistencia_payload' => $result->payload,
        ])->save();

        return $result;
    }
}

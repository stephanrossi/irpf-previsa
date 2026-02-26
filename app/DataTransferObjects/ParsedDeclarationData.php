<?php

namespace App\DataTransferObjects;

class ParsedDeclarationData
{
    public function __construct(
        public readonly string $nome,
        public readonly string $cpf,
        public readonly int $anoBase,
        public readonly int $exercicio,
        public readonly string $tipo,
        public readonly float $totalRendTributaveis,
        public readonly float $totalRendExclusiva,
        public readonly float $totalRendRecebidosAcumuladamente,
        public readonly float $totalRendaIsenta,
        public readonly float $totalBensImoveis,
        public readonly float $totalDividasOnus,
        public readonly float $totalBensAdquiridosAno,
        public readonly float $totalBensAnoAnterior = 0.0,
        public readonly float $totalBensAnoAtual = 0.0,
        public readonly float $totalBensReais = 0.0,
        public readonly float $totalDividasAnoAnterior = 0.0,
        public readonly float $totalDividasAnoAtual = 0.0,
        public readonly float $totalDividasOnusReais = 0.0,
        public readonly float $rendTribPj = 0.0,
        public readonly float $rendTribPfExterior = 0.0,
        public readonly float $detailedDebtsTotal = 0.0,
        public readonly array $isentosDetalhados = [],
        public readonly float $totalPlanosSaude = 0.0,
        public readonly float $totalDespesasMedicasOdont = 0.0,
        public readonly float $totalDespesasInstrucao = 0.0,
        public readonly float $totalPensaoJudicial = 0.0,
        public readonly float $totalPgbl = 0.0,
        public readonly float $totalIrPago = 0.0,
        public readonly float $totalImpostoPagoRetido = 0.0,
        public readonly float $totalPagamentosEfetuados = 0.0,
        public readonly float $totalDoacoesEfetuadas = 0.0,
        public readonly float $totalDoacoesPartidosPoliticos = 0.0,
        public readonly float $totalAtividadeRuralResultadoTributavel = 0.0,
        public readonly float $totalRendaVariavel = 0.0,
        public readonly float $gastosDeclaradosTotal = 0.0,
        public readonly array $gastosDeclaradosBreakdown = [],
        public readonly int $complexityScore = 0,
        public readonly string $complexityLevel = 'baixa',
        public readonly array $complexityBreakdown = [],
        public readonly bool $isRetificadora = false,
        public readonly ?string $reciboAnterior = null,
    ) {
    }
}

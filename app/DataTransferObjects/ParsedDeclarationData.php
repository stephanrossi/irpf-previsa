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
        public readonly float $totalRendaIsenta,
        public readonly float $totalBensImoveis,
        public readonly float $totalDividasOnus,
        public readonly float $totalBensAdquiridosAno,
        public readonly float $detailedDebtsTotal = 0.0,
        public readonly array $isentosDetalhados = [],
        public readonly float $totalPlanosSaude = 0.0,
        public readonly float $totalDespesasMedicasOdont = 0.0,
        public readonly float $totalDespesasInstrucao = 0.0,
        public readonly float $totalPensaoJudicial = 0.0,
        public readonly float $totalPgbl = 0.0,
        public readonly float $totalIrPago = 0.0,
        public readonly float $gastosDeclaradosTotal = 0.0,
        public readonly array $gastosDeclaradosBreakdown = [],
    ) {
    }
}

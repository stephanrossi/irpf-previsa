<?php

namespace App\DataTransferObjects;

class IrpfInconsistencyResult
{
    public function __construct(
        public readonly float $rendaTributavel,
        public readonly float $rendaIsenta,
        public readonly float $gastosEstimados,
        public readonly float $totalBensAdquiridos,
        public readonly float $variacaoDescoberto,
        public readonly bool $risco,
        public readonly array $payload = [],
    ) {
    }
}

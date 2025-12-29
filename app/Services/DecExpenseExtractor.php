<?php

namespace App\Services;

class DecExpenseExtractor
{
    private array $categories = [
        'planos_saude' => ['codes' => ['26']],
        'medicas_odont' => ['codes' => ['09','10','11','12','13','14','15','16','17','18','19','20','21','22']],
        'instrucao' => ['codes' => ['01','02']],
        'pensao_judicial' => ['codes' => ['30','31']],
        'pgbl' => ['codes' => ['36','37']],
    ];

    private array $totals = [];
    private float $totalIrPago = 0.0;

    public function __construct()
    {
        foreach (array_keys($this->categories) as $key) {
            $this->totals[$key] = [
                'codes' => $this->categories[$key]['codes'],
                'bruto' => 0.0,
                'reducao' => 0.0,
                'liquido' => 0.0,
                'itens' => 0,
            ];
        }
    }

    public function addPaymentLine(string $line): void
    {
        $code = $this->slice($line, 14, 15);
        $vrPagto = $this->parseMoney($this->slice($line, 106, 118));
        $vrReduc = $this->parseMoney($this->slice($line, 119, 131));
        $liquido = max(0, $vrPagto - $vrReduc);

        foreach ($this->totals as $key => &$info) {
            if (in_array($code, $info['codes'], true)) {
                $info['bruto'] += $vrPagto;
                $info['reducao'] += $vrReduc;
                $info['liquido'] += $liquido;
                $info['itens'] += 1;
                break;
            }
        }
    }

    public function setIrPago(float $valor): void
    {
        $this->totalIrPago = $valor;
    }

    public function result(): array
    {
        $gastosDeclaradosTotal = $this->totalIrPago;
        foreach ($this->totals as $info) {
            $gastosDeclaradosTotal += $info['liquido'];
        }

        return [
            'total_planos_saude' => $this->totals['planos_saude']['liquido'],
            'total_despesas_medicas_odont' => $this->totals['medicas_odont']['liquido'],
            'total_despesas_instrucao' => $this->totals['instrucao']['liquido'],
            'total_pensao_judicial' => $this->totals['pensao_judicial']['liquido'],
            'total_pgbl' => $this->totals['pgbl']['liquido'],
            'total_ir_pago' => $this->totalIrPago,
            'gastos_declarados_total' => $gastosDeclaradosTotal,
            'gastos_declarados_breakdown' => array_merge($this->totals, [
                'ir_pago' => [
                    'fonte' => 'reg20.VR_TOTIMPPAGO',
                    'liquido' => $this->totalIrPago,
                ],
            ]),
        ];
    }

    private function slice(string $line, int $start, int $end): string
    {
        $length = $end - $start + 1;

        return substr($line, $start - 1, $length) ?: '';
    }

    private function parseMoney(string $value): float
    {
        $clean = trim($value);

        if ($clean === '') {
            return 0.0;
        }

        $negative = str_contains($clean, '-');
        $digits = preg_replace('/\D/', '', $clean);

        if ($digits === '' || $digits === null) {
            return 0.0;
        }

        $amount = (int) $digits / 100;

        return $negative ? -$amount : $amount;
    }
}

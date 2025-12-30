<?php

namespace App\Services;

use App\DataTransferObjects\ParsedDeclarationData;
use App\Services\DecExpenseExtractor;
use InvalidArgumentException;
use RuntimeException;
use SplFileObject;

class ParseDecFileService
{
    public function parse(string $path): ParsedDeclarationData
    {
        if (! is_readable($path)) {
            throw new InvalidArgumentException('DEC file path is not readable.');
        }

        $file = new SplFileObject($path, 'r');
        $expenses = new DecExpenseExtractor();
        $header = null;
        $hasReg20 = false;
        $totalRendTributaveis = 0.0;
        $totalRendaIsenta = 0.0;
        $declaredDividas = null;
        $detailedDebts = 0.0;
        $bensImoveis = 0.0;
        $bensAdquiridosAno = 0.0;
        $bensAdquiridosFallback = 0.0;
        $isentos = [];
        $totalIrPago = 0.0;

        foreach ($file as $line) {
            if ($line === false || $line === null) {
                continue;
            }

            $line = rtrim((string) $line, "\r\n");
            if ($line === '') {
                continue;
            }

            $line = str_pad($line, 1200);
            $prefix = substr($line, 0, 2);

            if ($prefix === 'IR') {
                $header = [
                    'exercicio' => (int) $this->slice($line, 9, 12),
                    'ano_base' => (int) $this->slice($line, 13, 16),
                    'cpf' => $this->onlyDigits($this->slice($line, 22, 32)),
                    'nome' => trim($this->slice($line, 40, 99)),
                    'in_completa' => strtoupper(trim($this->slice($line, 121, 121))) === 'S',
                    'is_retificadora' => trim($this->slice($line, 21, 21)) === '1',
                    'recibo_anterior' => trim($this->slice($line, 124, 133)) ?: null,
                ];

                continue;
            }

            if ($prefix === '20') {
                $hasReg20 = true;
                $totalRendTributaveis = $this->parseMoney($this->slice($line, 66, 78));
                $totalRendaIsenta = $this->parseMoney($this->slice($line, 470, 482));
                $declaredDividas = $this->parseMoney($this->slice($line, 444, 456));
                $totalIrPago = $this->parseMoney($this->slice($line, 352, 364));
                $expenses->setIrPago($totalIrPago);
                continue;
            }

            if ($prefix === '18' && ! $hasReg20) {
                $totalRendTributaveis = $this->parseMoney($this->slice($line, 14, 26));
                $totalRendaIsenta = $this->parseMoney($this->slice($line, 158, 170));
                $declaredDividas = $this->parseMoney($this->slice($line, 379, 391));
                continue;
            }

            if ($prefix === '27') {
                $grupoBem = $this->slice($line, 1101, 1102);
                $vrAtual = $this->parseMoney($this->slice($line, 545, 557));
                $vrAnterior = $this->parseMoney($this->slice($line, 532, 544));
                $dtAquisicao = $this->slice($line, 897, 904);

                if ($grupoBem === '01') {
                    $bensImoveis += $vrAtual;
                }

                $anoData = substr($dtAquisicao, -4);
                if ($dtAquisicao !== '00000000' && $header && (int) $anoData === $header['ano_base']) {
                    $bensAdquiridosAno += $vrAtual;
                }

                if ($vrAnterior === 0.0 && $vrAtual > 0.0) {
                    $bensAdquiridosFallback += $vrAtual;
                }
            }

            if ($prefix === '28') {
                $detailedDebts += $this->parseMoney($this->slice($line, 541, 553));
            }

            if ($prefix === '23') {
                $codIsento = (int) $this->parseInt($this->slice($line, 14, 17));
                $valor = $this->parseMoney($this->slice($line, 18, 30));
                if ($valor !== 0.0) {
                    $isentos[] = ['codigo' => $codIsento, 'valor' => $valor];
                }
            }

            if ($prefix === '26') {
                $expenses->addPaymentLine($line);
            }
        }

        if (! $header) {
            throw new RuntimeException('DEC header not found or invalid.');
        }

        $tipo = $header['in_completa'] ? 'completa' : 'simplificada';
        $totalDividasOnus = $declaredDividas ?? 0.0;
        $bensAdquiridos = $bensAdquiridosAno > 0 ? $bensAdquiridosAno : $bensAdquiridosFallback;
        $expenseTotals = $expenses->result();

        return new ParsedDeclarationData(
            nome: $header['nome'],
            cpf: $header['cpf'],
            anoBase: $header['ano_base'],
            exercicio: $header['exercicio'],
            tipo: $tipo,
            totalRendTributaveis: $totalRendTributaveis,
            totalRendaIsenta: $totalRendaIsenta,
            totalBensImoveis: $bensImoveis,
            totalDividasOnus: $totalDividasOnus,
            totalBensAdquiridosAno: $bensAdquiridos,
            detailedDebtsTotal: $detailedDebts,
            isentosDetalhados: $isentos,
            totalPlanosSaude: $expenseTotals['total_planos_saude'],
            totalDespesasMedicasOdont: $expenseTotals['total_despesas_medicas_odont'],
            totalDespesasInstrucao: $expenseTotals['total_despesas_instrucao'],
            totalPensaoJudicial: $expenseTotals['total_pensao_judicial'],
            totalPgbl: $expenseTotals['total_pgbl'],
            totalIrPago: $expenseTotals['total_ir_pago'],
            gastosDeclaradosTotal: $expenseTotals['gastos_declarados_total'],
            gastosDeclaradosBreakdown: $expenseTotals['gastos_declarados_breakdown'],
            isRetificadora: $header['is_retificadora'],
            reciboAnterior: $header['recibo_anterior'],
        );
    }

    public function parseHeader(string $path): array
    {
        if (! is_readable($path)) {
            throw new InvalidArgumentException('DEC file path is not readable.');
        }
        $file = new SplFileObject($path, 'r');
        foreach ($file as $line) {
            if ($line === false || $line === null) {
                continue;
            }
            $line = rtrim((string) $line, "\r\n");
            if ($line === '') {
                continue;
            }
            $line = str_pad($line, 1200);
            $prefix = substr($line, 0, 2);
            if ($prefix === 'IR') {
                return [
                    'cpf' => $this->onlyDigits($this->slice($line, 22, 32)),
                    'nome' => trim($this->slice($line, 40, 99)),
                    'ano_base' => (int) $this->slice($line, 13, 16),
                    'exercicio' => (int) $this->slice($line, 9, 12),
                    'is_retificadora' => trim($this->slice($line, 21, 21)) === '1',
                    'recibo_anterior' => trim($this->slice($line, 124, 133)) ?: null,
                ];
            }
        }

        throw new RuntimeException('DEC header not found or invalid.');
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

    private function parseInt(string $value): int
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';

        return (int) $digits;
    }

    private function onlyDigits(string $value): string
    {
        return preg_replace('/\D/', '', $value) ?? '';
    }
}

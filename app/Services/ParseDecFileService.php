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
        $totalRendExclusiva = 0.0;
        $totalRendRecebidosAcumuladamente = 0.0;
        $totalDoacoesPartidosPoliticos = 0.0;
        $totalAtividadeRuralResultadoTributavel = 0.0;
        $totalRendaVariavel = 0.0;
        $totalBensAnoAnteriorReg27 = 0.0;
        $totalBensAnoAtualReg27 = 0.0;
        $totalDividasAnoAnteriorReg28 = 0.0;
        $totalDividasAnoAtualReg28 = 0.0;
        $rendTribPj = 0.0;
        $rendTribPfExterior = 0.0;
        $hasRendimentosBreakdown = false;
        $totalRendaIsenta = 0.0;
        $declaredDividas = null;
        $detailedDebts = 0.0;
        $bensImoveis = 0.0;
        $bensAdquiridosAno = 0.0;
        $bensAdquiridosFallback = 0.0;
        $isentos = [];
        $totalIrPago = 0.0;
        $totalIrRraRetido = 0.0;
        $totalIrFonteRendPj = 0.0;
        $totalCarneLeao = 0.0;

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
                $totalAtividadeRuralResultadoTributavel = $this->parseMoney($this->slice($line, 53, 65));
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

            // Registro 21: Rendimentos Tributaveis Recebidos de PJ (uma linha por fonte pagadora)
            // Formula: Rend Receb PJ - Prev Oficial - IR Fonte + 13o - IRRF 13o
            // Posicoes baseadas no leiaute padrao IRPF (campos monetarios de 13 chars):
            //   88-100: Rendimentos recebidos de PJ
            //   101-113: Contribuicao Previdenciaria Oficial
            //   114-126: 13o Salario
            //   127-139: Imposto Retido na Fonte
            //   148-160: IRRF sobre 13o Salario
            if ($prefix === '21') {
                $hasRendimentosBreakdown = true;
                $rendPj       = $this->parseMoney($this->slice($line, 88, 100));
                $prevOficial  = $this->parseMoney($this->slice($line, 101, 113));
                $decimoTerc   = $this->parseMoney($this->slice($line, 114, 126));
                $irFonte      = $this->parseMoney($this->slice($line, 127, 139));
                $irrfDecimoT  = $this->parseMoney($this->slice($line, 148, 160));

                $rendTribPj += $rendPj - $prevOficial - $irFonte + $decimoTerc - $irrfDecimoT;
                $totalIrFonteRendPj += $irFonte;
            }

            // Registro 22: Rendimentos Tributaveis Recebidos de PF e do Exterior
            // Formula: rendimentos - deducoes - carne-leao (uma linha por mes).
            // Rendimentos: 28-40 + 41-53 + 54-66 + 67-79
            // Deducoes: 80-92 + 106-118 + 119-131; Carne-leao: 145-157
            if ($prefix === '22') {
                $hasRendimentosBreakdown = true;
                $rendimentosMes = $this->parseMoney($this->slice($line, 28, 40))
                    + $this->parseMoney($this->slice($line, 41, 53))
                    + $this->parseMoney($this->slice($line, 54, 66))
                    + $this->parseMoney($this->slice($line, 67, 79));

                $deducoesMes = $this->parseMoney($this->slice($line, 80, 92))
                    + $this->parseMoney($this->slice($line, 106, 118))
                    + $this->parseMoney($this->slice($line, 119, 131));

                $carneLeaoMes = $this->parseMoney($this->slice($line, 145, 157));

                $rendTribPfExterior += $rendimentosMes - $deducoesMes - $carneLeaoMes;
                $totalCarneLeao += $carneLeaoMes;
            }

            if ($prefix === '27') {
                $grupoBem = $this->slice($line, 1101, 1102);
                $vrAtual = $this->parseMoney($this->slice($line, 545, 557));
                $vrAnterior = $this->parseMoney($this->slice($line, 532, 544));
                $dtAquisicao = $this->slice($line, 897, 904);
                $totalBensAnoAnteriorReg27 += $vrAnterior;
                $totalBensAnoAtualReg27 += $vrAtual;

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
                $situacaoAnoAnterior = $this->parseMoney($this->slice($line, 528, 540));
                $situacaoAnoAtual = $this->parseMoney($this->slice($line, 541, 553));
                $detailedDebts += $situacaoAnoAtual;
                $totalDividasAnoAnteriorReg28 += $situacaoAnoAnterior;
                $totalDividasAnoAtualReg28 += $situacaoAnoAtual;
            }

            if ($prefix === '23') {
                $codIsento = (int) $this->parseInt($this->slice($line, 14, 17));
                $valor = $this->parseMoney($this->slice($line, 18, 30));
                if ($valor !== 0.0) {
                    $isentos[] = ['codigo' => $codIsento, 'valor' => $valor];
                }
            }

            // Registro 24: Rendimentos sujeitos a tributacao exclusiva/definitiva.
            // Soma dos valores por item.
            if ($prefix === '24') {
                $totalRendExclusiva += $this->parseMoney($this->slice($line, 18, 30));
            }

            // Registro 45: Rendimentos Tributaveis de PJ Recebidos Acumuladamente (RRA).
            // Campos:
            //   90-102: Rendimentos Recebidos
            //   129-141: Imposto Retido na Fonte (RRA)
            if ($prefix === '45') {
                $totalRendRecebidosAcumuladamente += $this->parseMoney($this->slice($line, 90, 102));
                $totalIrRraRetido += $this->parseMoney($this->slice($line, 129, 141));
            }

            if ($prefix === '26') {
                $expenses->addPaymentLine($line);
            }

            // Registro 40: Renda Variavel - Operacoes Comuns / Day Trade (mes a mes).
            // Campos usados:
            //   14-15: mes (01-12)
            //   16-28: Base de calculo do imposto - Operacoes Comuns
            //   55-67: Base de calculo do imposto - Day Trade
            // Regra solicitada:
            //   Jan-Nov: (base comum - 15%) + (base day trade - 20%)
            //   Dez: somente as bases (sem diminuir imposto).
            if ($prefix === '40') {
                $mes = $this->parseInt($this->slice($line, 14, 15));
                $baseOperacoesComuns = max(0.0, $this->parseMoney($this->slice($line, 16, 28)));
                $baseDayTrade = max(0.0, $this->parseMoney($this->slice($line, 55, 67)));

                if ($mes >= 1 && $mes <= 12) {
                    if ($mes === 12) {
                        $totalRendaVariavel += $baseOperacoesComuns + $baseDayTrade;
                    } else {
                        $totalRendaVariavel += ($baseOperacoesComuns * 0.85) + ($baseDayTrade * 0.80);
                    }
                }
            }
        }

        if (! $header) {
            throw new RuntimeException('DEC header not found or invalid.');
        }

        $somaRendTributaveis = $rendTribPj + $rendTribPfExterior;
        if ($hasRendimentosBreakdown || $somaRendTributaveis !== 0.0) {
            $totalRendTributaveis = $somaRendTributaveis;
        }
        $totalRendRecebidosAcumuladamente -= $totalIrRraRetido;
        $totalImpostoPagoRetido = $totalIrPago > 0
            ? max(0.0, $totalIrPago - $totalIrRraRetido)
            : ($totalIrFonteRendPj + $totalCarneLeao);

        $tipo = $header['in_completa'] ? 'completa' : 'simplificada';
        $totalDividasOnus = $declaredDividas ?? 0.0;
        $totalBensAnoAnterior = $totalBensAnoAnteriorReg27;
        $totalBensAnoAtual = $totalBensAnoAtualReg27;
        $totalBensReais = $totalBensAnoAtual - $totalBensAnoAnterior;
        $totalDividasAnoAnterior = $totalDividasAnoAnteriorReg28;
        $totalDividasAnoAtual = $totalDividasAnoAtualReg28 > 0 ? $totalDividasAnoAtualReg28 : $totalDividasOnus;
        $totalDividasOnusReais = $totalDividasAnoAtual - $totalDividasAnoAnterior;
        $bensAdquiridos = $bensAdquiridosAno > 0 ? $bensAdquiridosAno : $bensAdquiridosFallback;
        $expenseTotals = $expenses->result();

        return new ParsedDeclarationData(
            nome: $header['nome'],
            cpf: $header['cpf'],
            anoBase: $header['ano_base'],
            exercicio: $header['exercicio'],
            tipo: $tipo,
            totalRendTributaveis: $totalRendTributaveis,
            totalRendExclusiva: $totalRendExclusiva,
            totalRendRecebidosAcumuladamente: $totalRendRecebidosAcumuladamente,
            totalRendaIsenta: $totalRendaIsenta,
            rendTribPj: $rendTribPj,
            rendTribPfExterior: $rendTribPfExterior,
            totalBensImoveis: $bensImoveis,
            totalDividasOnus: $totalDividasOnus,
            totalBensAnoAnterior: $totalBensAnoAnterior,
            totalBensAnoAtual: $totalBensAnoAtual,
            totalBensReais: $totalBensReais,
            totalDividasAnoAnterior: $totalDividasAnoAnterior,
            totalDividasAnoAtual: $totalDividasAnoAtual,
            totalDividasOnusReais: $totalDividasOnusReais,
            totalBensAdquiridosAno: $bensAdquiridos,
            detailedDebtsTotal: $detailedDebts,
            isentosDetalhados: $isentos,
            totalPlanosSaude: $expenseTotals['total_planos_saude'],
            totalDespesasMedicasOdont: $expenseTotals['total_despesas_medicas_odont'],
            totalDespesasInstrucao: $expenseTotals['total_despesas_instrucao'],
            totalPensaoJudicial: $expenseTotals['total_pensao_judicial'],
            totalPgbl: $expenseTotals['total_pgbl'],
            totalIrPago: $expenseTotals['total_ir_pago'],
            totalImpostoPagoRetido: $totalImpostoPagoRetido,
            totalPagamentosEfetuados: $expenseTotals['total_pagamentos_efetuados'],
            totalDoacoesEfetuadas: $expenseTotals['total_doacoes_efetuadas'],
            totalDoacoesPartidosPoliticos: $totalDoacoesPartidosPoliticos,
            totalAtividadeRuralResultadoTributavel: $totalAtividadeRuralResultadoTributavel,
            totalRendaVariavel: $totalRendaVariavel,
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

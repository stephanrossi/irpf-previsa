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
        $fontesPagadorasCount = 0;
        $dependentesCount = 0;
        $despesasCount = 0;
        $bensCount = 0;
        $dividasCount = 0;
        $rraCount = 0;
        $isentosCount = 0;
        $tributacaoExclusivaCount = 0;
        $doacoesCount = 0;
        $hasCarneLeao = false;
        $hasRendaVariavel = false;
        $hasAtividadeRural = false;

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
                $fontesPagadorasCount++;
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
                if ($carneLeaoMes > 0) {
                    $hasCarneLeao = true;
                }
            }

            if ($prefix === '25') {
                $dependentesCount++;
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
                if ($vrAtual > 0) {
                    $bensCount++;
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
                $dividasCount++;
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
                    $isentosCount++;
                    $isentos[] = ['codigo' => $codIsento, 'valor' => $valor];
                }
            }

            // Registro 24: Rendimentos sujeitos a tributacao exclusiva/definitiva.
            // Soma dos valores por item.
            if ($prefix === '24') {
                $valorExclusiva = $this->parseMoney($this->slice($line, 18, 30));
                $totalRendExclusiva += $valorExclusiva;
                if ($valorExclusiva !== 0.0) {
                    $tributacaoExclusivaCount++;
                }
            }

            // Registro 45: Rendimentos Tributaveis de PJ Recebidos Acumuladamente (RRA).
            // Campos:
            //   90-102: Rendimentos Recebidos
            //   129-141: Imposto Retido na Fonte (RRA)
            if ($prefix === '45') {
                $rraCount++;
                $totalRendRecebidosAcumuladamente += $this->parseMoney($this->slice($line, 90, 102));
                $totalIrRraRetido += $this->parseMoney($this->slice($line, 129, 141));
            }

            if ($prefix === '26') {
                $despesasCount++;
                $codigoDespesa = $this->slice($line, 14, 15);
                if (in_array($codigoDespesa, ['80', '81'], true)) {
                    $doacoesCount++;
                }
                $expenses->addPaymentLine($line);
            }

            // Registro 40: Renda Variavel - Resultados (Operacoes Comuns / Day Trade).
            // Campos usados (leiaute DIRPF):
            //   14-15: mes (01-12)
            //   94-106: Base de Calculo do Imposto - Operacoes Comuns
            //   107-119: Base de Calculo do Imposto - Day Trade
            //   120-124: Aliquota do Imposto - Operacoes Comuns
            //   125-129: Aliquota do Imposto - Day Trade
            // Regra:
            //   Jan-Nov: valor liquido = base * (1 - aliquota/100), por coluna.
            //   Dez: considera somente as bases (sem reduzir aliquota).
            //   Quando a aliquota vier zerada/ausente no arquivo, usar padrao 15% (comuns) e 20% (day trade).
            if ($prefix === '40') {
                $hasRendaVariavel = true;
                $mes = $this->parseInt($this->slice($line, 14, 15));
                $baseOperacoesComuns = max(0.0, $this->parseMoney($this->slice($line, 94, 106)));
                $baseDayTrade = max(0.0, $this->parseMoney($this->slice($line, 107, 119)));
                $aliquotaOperacoesComuns = $this->parseAliquotaPercent($this->slice($line, 120, 124), 15.0);
                $aliquotaDayTrade = $this->parseAliquotaPercent($this->slice($line, 125, 129), 20.0);

                if ($mes >= 1 && $mes <= 12) {
                    if ($mes === 12) {
                        $totalRendaVariavel += $baseOperacoesComuns + $baseDayTrade;
                    } else {
                        $fatorComuns = max(0.0, 1 - ($aliquotaOperacoesComuns / 100));
                        $fatorDayTrade = max(0.0, 1 - ($aliquotaDayTrade / 100));
                        $totalRendaVariavel += ($baseOperacoesComuns * $fatorComuns) + ($baseDayTrade * $fatorDayTrade);
                    }
                }
            }

            if ($prefix === '41' || $prefix === '42') {
                $hasRendaVariavel = true;
            }

            if ($prefix === '49' && preg_match('/[1-9]/', substr($line, 13))) {
                $hasCarneLeao = true;
            }

            if ($prefix === '50' && preg_match('/[1-9]/', substr($line, 13))) {
                $hasAtividadeRural = true;
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
        if ($totalCarneLeao > 0) {
            $hasCarneLeao = true;
        }
        if ($totalAtividadeRuralResultadoTributavel > 0) {
            $hasAtividadeRural = true;
        }
        $complexityScore = $fontesPagadorasCount
            + $dependentesCount
            + $despesasCount
            + $bensCount
            + $dividasCount
            + $rraCount
            + ($hasCarneLeao ? 1 : 0)
            + $isentosCount
            + $tributacaoExclusivaCount
            + $doacoesCount
            + ($hasRendaVariavel ? 20 : 0)
            + ($hasAtividadeRural ? 10 : 0);
        $complexityLevel = $this->classifyComplexity($complexityScore);
        $complexityBreakdown = $this->buildComplexityBreakdown(
            fontesPagadorasCount: $fontesPagadorasCount,
            dependentesCount: $dependentesCount,
            despesasCount: $despesasCount,
            bensCount: $bensCount,
            dividasCount: $dividasCount,
            rraCount: $rraCount,
            hasCarneLeao: $hasCarneLeao,
            isentosCount: $isentosCount,
            tributacaoExclusivaCount: $tributacaoExclusivaCount,
            doacoesCount: $doacoesCount,
            hasRendaVariavel: $hasRendaVariavel,
            hasAtividadeRural: $hasAtividadeRural,
        );
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
            complexityScore: $complexityScore,
            complexityLevel: $complexityLevel,
            complexityBreakdown: $complexityBreakdown,
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

    private function parseAliquotaPercent(string $value, float $default): float
    {
        $clean = trim($value);
        if ($clean === '') {
            return $default;
        }

        // Aceita formatos com pontuacao (ex.: 15,00 / 15.00) e formato numerico compacto (ex.: 01500).
        $normalized = str_replace(',', '.', preg_replace('/[^0-9,.\-]/', '', $clean) ?? '');
        if ($normalized !== '' && is_numeric($normalized)) {
            $aliquota = (float) $normalized;
            if ($aliquota > 100) {
                $aliquota /= 100;
            }

            if ($aliquota > 0) {
                return min(100.0, $aliquota);
            }
        }

        $digits = preg_replace('/\D/', '', $clean);
        if ($digits === null || $digits === '') {
            return $default;
        }

        $aliquota = (float) $digits;
        if ($aliquota > 100) {
            $aliquota /= 100;
        }

        if ($aliquota <= 0) {
            return $default;
        }

        return min(100.0, $aliquota);
    }

    private function onlyDigits(string $value): string
    {
        return preg_replace('/\D/', '', $value) ?? '';
    }

    private function classifyComplexity(int $score): string
    {
        if ($score >= 30) {
            return 'alta';
        }

        if ($score >= 10) {
            return 'media';
        }

        return 'baixa';
    }

    private function buildComplexityBreakdown(
        int $fontesPagadorasCount,
        int $dependentesCount,
        int $despesasCount,
        int $bensCount,
        int $dividasCount,
        int $rraCount,
        bool $hasCarneLeao,
        int $isentosCount,
        int $tributacaoExclusivaCount,
        int $doacoesCount,
        bool $hasRendaVariavel,
        bool $hasAtividadeRural
    ): array {
        return [
            [
                'key' => 'fontes_pagadoras',
                'label' => 'Fontes pagadoras',
                'points' => $fontesPagadorasCount,
                'base' => $fontesPagadorasCount,
                'multiplier' => 1,
            ],
            [
                'key' => 'dependentes',
                'label' => 'Dependentes',
                'points' => $dependentesCount,
                'base' => $dependentesCount,
                'multiplier' => 1,
            ],
            [
                'key' => 'despesas',
                'label' => 'Despesas',
                'points' => $despesasCount,
                'base' => $despesasCount,
                'multiplier' => 1,
            ],
            [
                'key' => 'bens_direitos',
                'label' => 'Bens e direitos (valor > 0)',
                'points' => $bensCount,
                'base' => $bensCount,
                'multiplier' => 1,
            ],
            [
                'key' => 'dividas',
                'label' => 'Dividas',
                'points' => $dividasCount,
                'base' => $dividasCount,
                'multiplier' => 1,
            ],
            [
                'key' => 'rra',
                'label' => 'Rendimentos acumulados (RRA)',
                'points' => $rraCount,
                'base' => $rraCount,
                'multiplier' => 1,
            ],
            [
                'key' => 'carne_leao',
                'label' => 'Carne-leao',
                'points' => $hasCarneLeao ? 1 : 0,
                'base' => $hasCarneLeao ? 1 : 0,
                'multiplier' => 1,
            ],
            [
                'key' => 'rendimentos_isentos',
                'label' => 'Rendimentos isentos',
                'points' => $isentosCount,
                'base' => $isentosCount,
                'multiplier' => 1,
            ],
            [
                'key' => 'tributacao_exclusiva',
                'label' => 'Rendimentos de tributacao exclusiva',
                'points' => $tributacaoExclusivaCount,
                'base' => $tributacaoExclusivaCount,
                'multiplier' => 1,
            ],
            [
                'key' => 'doacoes_efetuadas',
                'label' => 'Doacoes efetuadas',
                'points' => $doacoesCount,
                'base' => $doacoesCount,
                'multiplier' => 1,
            ],
            [
                'key' => 'renda_variavel',
                'label' => 'Renda variavel / acoes / FIIs',
                'points' => $hasRendaVariavel ? 20 : 0,
                'base' => $hasRendaVariavel ? 1 : 0,
                'multiplier' => 20,
            ],
            [
                'key' => 'atividade_rural',
                'label' => 'Atividade rural',
                'points' => $hasAtividadeRural ? 10 : 0,
                'base' => $hasAtividadeRural ? 1 : 0,
                'multiplier' => 10,
            ],
        ];
    }
}

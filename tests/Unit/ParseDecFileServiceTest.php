<?php

namespace Tests\Unit;

use App\Services\ParseDecFileService;
use PHPUnit\Framework\Attributes\After;
use Tests\TestCase;

class ParseDecFileServiceTest extends TestCase
{
    private ParseDecFileService $service;

    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ParseDecFileService();
    }

    #[After]
    public function cleanupFiles(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    public function test_parses_header_fields(): void
    {
        $lines = [
            $this->headerLine(exercicio: 2024, anoBase: 2023, cpf: '12345678901', nome: 'Maria Teste', completa: true),
        ];

        $path = $this->writeTempDec($lines);
        $data = $this->service->parse($path);

        $this->assertSame('Maria Teste', $data->nome);
        $this->assertSame('12345678901', $data->cpf);
        $this->assertSame(2024, $data->exercicio);
        $this->assertSame(2023, $data->anoBase);
        $this->assertSame('completa', $data->tipo);
        $this->assertFalse($data->isRetificadora);
        $this->assertNull($data->reciboAnterior);
    }

    public function test_parses_money_with_leading_zeros(): void
    {
        $lines = [
            $this->headerLine(),
            $this->buildLine('20', [
                [66, 78, str_pad('12345', 13, '0', STR_PAD_LEFT)],
                [53, 65, str_pad('3210', 13, '0', STR_PAD_LEFT)],
                [470, 482, str_pad('300', 13, '0', STR_PAD_LEFT)],
                [444, 456, str_pad('5678', 13, '0', STR_PAD_LEFT)],
            ]),
        ];

        $data = $this->service->parse($this->writeTempDec($lines));

        $this->assertEqualsWithDelta(123.45, $data->totalRendTributaveis, 0.001);
        $this->assertEqualsWithDelta(56.78, $data->totalDividasOnus, 0.001);
        $this->assertEqualsWithDelta(3.00, $data->totalRendaIsenta, 0.001);
        $this->assertEqualsWithDelta(32.10, $data->totalAtividadeRuralResultadoTributavel, 0.001);
    }

    public function test_sums_bens_imoveis_by_group_01(): void
    {
        $lines = [
            $this->headerLine(anoBase: 2024),
            $this->buildLine('27', [
                [545, 557, str_pad('1000', 13, '0', STR_PAD_LEFT)], // 10.00
                [1101, 1102, '01'],
            ]),
            $this->buildLine('27', [
                [545, 557, str_pad('2500', 13, '0', STR_PAD_LEFT)], // 25.00
                [1101, 1102, '01'],
            ]),
            $this->buildLine('27', [
                [545, 557, str_pad('9999', 13, '0', STR_PAD_LEFT)], // ignored group
                [1101, 1102, '02'],
            ]),
        ];

        $data = $this->service->parse($this->writeTempDec($lines));

        $this->assertEqualsWithDelta(35.0, $data->totalBensImoveis, 0.001);
    }

    public function test_calculates_bens_reais_as_ano_atual_minus_ano_anterior(): void
    {
        $lines = [
            $this->headerLine(anoBase: 2024),
            $this->buildLine('27', [
                [532, 544, str_pad('1000000', 13, '0', STR_PAD_LEFT)], // 10,000.00
                [545, 557, str_pad('1300000', 13, '0', STR_PAD_LEFT)], // 13,000.00
                [1101, 1102, '04'],
            ]),
            $this->buildLine('27', [
                [532, 544, str_pad('500000', 13, '0', STR_PAD_LEFT)], // 5,000.00
                [545, 557, str_pad('400000', 13, '0', STR_PAD_LEFT)], // 4,000.00
                [1101, 1102, '07'],
            ]),
        ];

        $data = $this->service->parse($this->writeTempDec($lines));

        $this->assertEqualsWithDelta(15000.00, $data->totalBensAnoAnterior, 0.001);
        $this->assertEqualsWithDelta(17000.00, $data->totalBensAnoAtual, 0.001);
        $this->assertEqualsWithDelta(2000.00, $data->totalBensReais, 0.001);
    }

    public function test_calculates_bens_adquiridos_by_date(): void
    {
        $lines = [
            $this->headerLine(anoBase: 2024),
            $this->buildLine('27', [
                [532, 544, str_pad('0100', 13, '0', STR_PAD_LEFT)], // vr_anterior
                [545, 557, str_pad('20000', 13, '0', STR_PAD_LEFT)], // 200.00
                [897, 904, '15082024'],
                [1101, 1102, '01'],
            ]),
        ];

        $data = $this->service->parse($this->writeTempDec($lines));

        $this->assertEqualsWithDelta(200.0, $data->totalBensAdquiridosAno, 0.001);
    }

    public function test_uses_fallback_bens_adquiridos_when_no_date(): void
    {
        $lines = [
            $this->headerLine(anoBase: 2024),
            $this->buildLine('27', [
                [532, 544, str_pad('0', 13, '0', STR_PAD_LEFT)], // vr_anterior 0
                [545, 557, str_pad('5000', 13, '0', STR_PAD_LEFT)], // 50.00
                [897, 904, '00000000'],
                [1101, 1102, '01'],
            ]),
        ];

        $data = $this->service->parse($this->writeTempDec($lines));

        $this->assertEqualsWithDelta(50.0, $data->totalBensAdquiridosAno, 0.001);
    }

    public function test_fallback_to_reg_18_when_20_missing(): void
    {
        $lines = [
            $this->headerLine(completa: false, anoBase: 2024),
            $this->buildLine('18', [
                [14, 26, str_pad('99999', 13, '0', STR_PAD_LEFT)], // rend trib
                [158, 170, str_pad('2000', 13, '0', STR_PAD_LEFT)], // renda isenta
                [379, 391, str_pad('12345', 13, '0', STR_PAD_LEFT)], // dividas
            ]),
        ];

        $data = $this->service->parse($this->writeTempDec($lines));

        $this->assertSame('simplificada', $data->tipo);
        $this->assertEqualsWithDelta(999.99, $data->totalRendTributaveis, 0.001);
        $this->assertEqualsWithDelta(123.45, $data->totalDividasOnus, 0.001);
        $this->assertEqualsWithDelta(20.0, $data->totalRendaIsenta, 0.001);
    }

    public function test_parses_reg_23_isentos_detail(): void
    {
        $lines = [
            $this->headerLine(retificadora: true, recibo: '1234567890'),
            $this->buildLine('20', [
                [66, 78, str_pad('10000', 13, '0', STR_PAD_LEFT)],
                [470, 482, str_pad('30000', 13, '0', STR_PAD_LEFT)],
            ]),
            $this->buildLine('23', [
                [14, 17, '1501'],
                [18, 30, str_pad('5000', 13, '0', STR_PAD_LEFT)],
            ]),
            $this->buildLine('23', [
                [14, 17, '1502'],
                [18, 30, str_pad('2000', 13, '0', STR_PAD_LEFT)],
            ]),
        ];

        $data = $this->service->parse($this->writeTempDec($lines));

        $this->assertEqualsWithDelta(300.00, $data->totalRendaIsenta, 0.001);
        $this->assertCount(2, $data->isentosDetalhados);
        $this->assertSame([['codigo' => 1501, 'valor' => 50.0], ['codigo' => 1502, 'valor' => 20.0]], $data->isentosDetalhados);
        $this->assertTrue($data->isRetificadora);
        $this->assertSame('1234567890', $data->reciboAnterior);
    }

    public function test_calculates_dividas_onus_reais_from_reg_28(): void
    {
        $lines = [
            $this->headerLine(),
            $this->buildLine('20', [
                [444, 456, str_pad('9000000', 13, '0', STR_PAD_LEFT)], // 90,000.00
            ]),
            $this->buildLine('28', [
                [528, 540, str_pad('3000000', 13, '0', STR_PAD_LEFT)], // 30,000.00
                [541, 553, str_pad('3500000', 13, '0', STR_PAD_LEFT)], // 35,000.00
                [554, 566, str_pad('500000', 13, '0', STR_PAD_LEFT)], // 5,000.00
            ]),
            $this->buildLine('28', [
                [528, 540, str_pad('500000', 13, '0', STR_PAD_LEFT)], // 5,000.00
                [541, 553, str_pad('1000000', 13, '0', STR_PAD_LEFT)], // 10,000.00
                [554, 566, str_pad('200000', 13, '0', STR_PAD_LEFT)], // 2,000.00
            ]),
        ];

        $data = $this->service->parse($this->writeTempDec($lines));

        $this->assertEqualsWithDelta(90000.00, $data->totalDividasOnus, 0.001);
        $this->assertEqualsWithDelta(35000.00, $data->totalDividasAnoAnterior, 0.001);
        $this->assertEqualsWithDelta(45000.00, $data->totalDividasAnoAtual, 0.001);
        $this->assertEqualsWithDelta(10000.00, $data->totalDividasOnusReais, 0.001);
    }

    public function test_sums_reg_24_rendimentos_exclusivos(): void
    {
        $lines = [
            $this->headerLine(),
            $this->buildLine('24', [
                [14, 17, '0006'],
                [18, 30, str_pad('5000', 13, '0', STR_PAD_LEFT)], // 50.00
            ]),
            $this->buildLine('24', [
                [14, 17, '0007'],
                [18, 30, str_pad('250', 13, '0', STR_PAD_LEFT)], // 2.50
            ]),
        ];

        $data = $this->service->parse($this->writeTempDec($lines));

        $this->assertEqualsWithDelta(52.50, $data->totalRendExclusiva, 0.001);
    }

    public function test_sums_reg_45_rendimentos_recebidos_acumuladamente_net_of_irrf(): void
    {
        $lines = [
            $this->headerLine(),
            $this->buildLine('45', [
                [90, 102, str_pad('1508182', 13, '0', STR_PAD_LEFT)], // 15,081.82
                [103, 115, str_pad('88000', 13, '0', STR_PAD_LEFT)], // 880.00
                [116, 128, str_pad('0', 13, '0', STR_PAD_LEFT)], // 0.00
                [129, 141, str_pad('46670', 13, '0', STR_PAD_LEFT)], // 466.70
            ]),
            $this->buildLine('45', [
                [90, 102, str_pad('1000', 13, '0', STR_PAD_LEFT)], // 10.00
            ]),
        ];

        $data = $this->service->parse($this->writeTempDec($lines));

        $this->assertEqualsWithDelta(14625.12, $data->totalRendRecebidosAcumuladamente, 0.001);
    }

    public function test_calculates_imposto_pago_retido_excluding_irrf_from_rra(): void
    {
        $lines = [
            $this->headerLine(),
            $this->buildLine('20', [
                [352, 364, str_pad('9574828', 13, '0', STR_PAD_LEFT)], // 95,748.28
            ]),
            $this->buildLine('45', [
                [90, 102, str_pad('440654', 13, '0', STR_PAD_LEFT)], // 4,406.54
                [129, 141, str_pad('29173', 13, '0', STR_PAD_LEFT)], // 291.73
            ]),
            $this->buildLine('45', [
                [90, 102, str_pad('441327', 13, '0', STR_PAD_LEFT)], // 4,413.27
                [129, 141, str_pad('29325', 13, '0', STR_PAD_LEFT)], // 293.25
            ]),
        ];

        $data = $this->service->parse($this->writeTempDec($lines));

        // 95,748.28 - (291.73 + 293.25) = 95,163.30
        $this->assertEqualsWithDelta(95163.30, $data->totalImpostoPagoRetido, 0.001);
    }

    public function test_calculates_renda_variavel_with_december_special_rule(): void
    {
        $lines = [
            $this->headerLine(),
            $this->buildLine('40', [
                [14, 15, '01'],
                [16, 28, str_pad('10000', 13, '0', STR_PAD_LEFT)], // base comum 100.00
                [55, 67, str_pad('5000', 13, '0', STR_PAD_LEFT)], // base day trade 50.00
            ]),
            $this->buildLine('40', [
                [14, 15, '02'],
                [16, 28, '-'.str_pad('10000', 12, '0', STR_PAD_LEFT)], // negativa -> deve zerar
                [55, 67, str_pad('2000', 13, '0', STR_PAD_LEFT)], // base day trade 20.00
            ]),
            $this->buildLine('40', [
                [14, 15, '12'],
                [16, 28, str_pad('30000', 13, '0', STR_PAD_LEFT)], // base comum 300.00
                [55, 67, str_pad('7000', 13, '0', STR_PAD_LEFT)], // base day trade 70.00
            ]),
        ];

        $data = $this->service->parse($this->writeTempDec($lines));

        // Jan: 100*0.85 + 50*0.80 = 125.00
        // Fev: 0*0.85 + 20*0.80 = 16.00 (base comum negativa desconsiderada)
        // Dez: 300 + 70 = 370.00 (sem desconto do imposto)
        $this->assertEqualsWithDelta(511.00, $data->totalRendaVariavel, 0.001);
    }

    public function test_sums_total_pagamentos_efetuados_from_reg_26(): void
    {
        $lines = [
            $this->headerLine(),
            $this->buildLine('26', [
                [14, 15, '10'],
                [106, 118, str_pad('75000', 13, '0', STR_PAD_LEFT)], // 750.00
                [119, 131, str_pad('0', 13, '0', STR_PAD_LEFT)],
            ]),
            $this->buildLine('26', [
                [14, 15, '26'],
                [106, 118, str_pad('2139509', 13, '0', STR_PAD_LEFT)], // 21,395.09
                [119, 131, str_pad('0', 13, '0', STR_PAD_LEFT)],
            ]),
        ];

        $data = $this->service->parse($this->writeTempDec($lines));

        $this->assertEqualsWithDelta(22145.09, $data->totalPagamentosEfetuados, 0.001);
    }

    public function test_sums_total_doacoes_efetuadas_from_reg_26_codes_80_81(): void
    {
        $lines = [
            $this->headerLine(),
            $this->buildLine('26', [
                [14, 15, '80'],
                [106, 118, str_pad('12345', 13, '0', STR_PAD_LEFT)], // 123.45
                [119, 131, str_pad('0', 13, '0', STR_PAD_LEFT)],
            ]),
            $this->buildLine('26', [
                [14, 15, '81'],
                [106, 118, str_pad('500', 13, '0', STR_PAD_LEFT)], // 5.00
                [119, 131, str_pad('0', 13, '0', STR_PAD_LEFT)],
            ]),
            $this->buildLine('26', [
                [14, 15, '21'],
                [106, 118, str_pad('10000', 13, '0', STR_PAD_LEFT)], // 100.00 (nao entra em doacoes)
                [119, 131, str_pad('0', 13, '0', STR_PAD_LEFT)],
            ]),
        ];

        $data = $this->service->parse($this->writeTempDec($lines));

        $this->assertEqualsWithDelta(128.45, $data->totalDoacoesEfetuadas, 0.001);
        $this->assertEqualsWithDelta(228.45, $data->totalPagamentosEfetuados, 0.001);
    }

    public function test_calculates_rendimentos_tributaveis_recebidos_de_pj_with_requested_formula(): void
    {
        $lines = [
            $this->headerLine(),
            $this->buildLine('21', [
                [88, 100, str_pad('100000', 13, '0', STR_PAD_LEFT)], // 1000.00
                [101, 113, str_pad('10000', 13, '0', STR_PAD_LEFT)], // 100.00
                [114, 126, str_pad('12000', 13, '0', STR_PAD_LEFT)], // 120.00
                [127, 139, str_pad('5000', 13, '0', STR_PAD_LEFT)], // 50.00
                [148, 160, str_pad('2000', 13, '0', STR_PAD_LEFT)], // 20.00
            ]),
            $this->buildLine('21', [
                [88, 100, str_pad('40000', 13, '0', STR_PAD_LEFT)], // 400.00
                [101, 113, str_pad('4000', 13, '0', STR_PAD_LEFT)], // 40.00
                [114, 126, str_pad('3000', 13, '0', STR_PAD_LEFT)], // 30.00
                [127, 139, str_pad('1000', 13, '0', STR_PAD_LEFT)], // 10.00
                [148, 160, str_pad('500', 13, '0', STR_PAD_LEFT)], // 5.00
            ]),
        ];

        $data = $this->service->parse($this->writeTempDec($lines));

        // Formula: Rend PJ - Prev Oficial - IR Fonte + 13o - IRRF 13o
        // Linha 1: 1000 - 100 - 50 + 120 - 20 = 950
        // Linha 2: 400 - 40 - 10 + 30 - 5 = 375
        $this->assertEqualsWithDelta(1325.0, $data->rendTribPj, 0.001);
    }

    public function test_calculates_rendimentos_tributaveis_recebidos_de_pf_exterior_with_requested_formula(): void
    {
        $lines = [
            $this->headerLine(),
            $this->buildLine('22', [
                [26, 27, '01'],
                [28, 40, str_pad('10000', 13, '0', STR_PAD_LEFT)], // 100.00
                [41, 53, str_pad('20000', 13, '0', STR_PAD_LEFT)], // 200.00
                [54, 66, str_pad('3000', 13, '0', STR_PAD_LEFT)], // 30.00
                [67, 79, str_pad('0', 13, '0', STR_PAD_LEFT)], // 0.00
                [80, 92, str_pad('1000', 13, '0', STR_PAD_LEFT)], // 10.00
                [93, 105, '2'], // quantidade dependentes (nao entra na formula)
                [106, 118, str_pad('500', 13, '0', STR_PAD_LEFT)], // 5.00
                [119, 131, str_pad('200', 13, '0', STR_PAD_LEFT)], // 2.00
                [145, 157, str_pad('1000', 13, '0', STR_PAD_LEFT)], // 10.00
            ]),
            $this->buildLine('22', [
                [26, 27, '02'],
                [28, 40, str_pad('5000', 13, '0', STR_PAD_LEFT)], // 50.00
                [41, 53, str_pad('5000', 13, '0', STR_PAD_LEFT)], // 50.00
                [54, 66, str_pad('0', 13, '0', STR_PAD_LEFT)], // 0.00
                [67, 79, str_pad('0', 13, '0', STR_PAD_LEFT)], // 0.00
                [80, 92, str_pad('0', 13, '0', STR_PAD_LEFT)], // 0.00
                [93, 105, '0'],
                [106, 118, str_pad('100', 13, '0', STR_PAD_LEFT)], // 1.00
                [119, 131, str_pad('0', 13, '0', STR_PAD_LEFT)], // 0.00
                [145, 157, str_pad('500', 13, '0', STR_PAD_LEFT)], // 5.00
            ]),
        ];

        $data = $this->service->parse($this->writeTempDec($lines));

        // Formula: rendimentos - deducoes - carne-leao
        // Linha 1: (100 + 200 + 30 + 0) - (10 + 5 + 2) - 10 = 303
        // Linha 2: (50 + 50 + 0 + 0) - (0 + 1 + 0) - 5 = 94
        $this->assertEqualsWithDelta(397.0, $data->rendTribPfExterior, 0.001);
    }

    public function test_uses_sum_of_pj_and_pf_exterior_for_total_rendimentos_tributaveis(): void
    {
        $lines = [
            $this->headerLine(),
            $this->buildLine('20', [
                [66, 78, str_pad('99999', 13, '0', STR_PAD_LEFT)], // 999.99 (must be overridden)
            ]),
            $this->buildLine('21', [
                [88, 100, str_pad('100000', 13, '0', STR_PAD_LEFT)], // 1000.00
                [101, 113, str_pad('10000', 13, '0', STR_PAD_LEFT)], // 100.00
                [114, 126, str_pad('12000', 13, '0', STR_PAD_LEFT)], // 120.00
                [127, 139, str_pad('5000', 13, '0', STR_PAD_LEFT)], // 50.00
                [148, 160, str_pad('2000', 13, '0', STR_PAD_LEFT)], // 20.00
            ]),
            $this->buildLine('22', [
                [26, 27, '01'],
                [28, 40, str_pad('10000', 13, '0', STR_PAD_LEFT)], // 100.00
                [41, 53, str_pad('20000', 13, '0', STR_PAD_LEFT)], // 200.00
                [54, 66, str_pad('3000', 13, '0', STR_PAD_LEFT)], // 30.00
                [67, 79, str_pad('0', 13, '0', STR_PAD_LEFT)], // 0.00
                [80, 92, str_pad('1000', 13, '0', STR_PAD_LEFT)], // 10.00
                [93, 105, '0'],
                [106, 118, str_pad('500', 13, '0', STR_PAD_LEFT)], // 5.00
                [119, 131, str_pad('200', 13, '0', STR_PAD_LEFT)], // 2.00
                [145, 157, str_pad('1000', 13, '0', STR_PAD_LEFT)], // 10.00
            ]),
        ];

        $data = $this->service->parse($this->writeTempDec($lines));

        // PJ: 1000 - 100 - 50 + 120 - 20 = 950
        // PF/Exterior: (100 + 200 + 30 + 0) - (10 + 5 + 2) - 10 = 303
        $this->assertEqualsWithDelta(950.0, $data->rendTribPj, 0.001);
        $this->assertEqualsWithDelta(303.0, $data->rendTribPfExterior, 0.001);
        $this->assertEqualsWithDelta(1253.0, $data->totalRendTributaveis, 0.001);
    }

    public function test_classifies_complexity_as_baixa_for_low_volume_declaration(): void
    {
        $lines = [
            $this->headerLine(),
            $this->buildLine('21', [
                [88, 100, str_pad('100000', 13, '0', STR_PAD_LEFT)],
                [101, 113, str_pad('10000', 13, '0', STR_PAD_LEFT)],
                [114, 126, str_pad('12000', 13, '0', STR_PAD_LEFT)],
                [127, 139, str_pad('5000', 13, '0', STR_PAD_LEFT)],
                [148, 160, str_pad('2000', 13, '0', STR_PAD_LEFT)],
            ]),
        ];

        $data = $this->service->parse($this->writeTempDec($lines));

        $this->assertSame(1, $data->complexityScore);
        $this->assertSame('baixa', $data->complexityLevel);
        $this->assertNotEmpty($data->complexityBreakdown);
    }

    public function test_calculates_complexity_score_and_classifies_as_alta(): void
    {
        $lines = [
            $this->headerLine(anoBase: 2024),
            $this->buildLine('21', [
                [88, 100, str_pad('100000', 13, '0', STR_PAD_LEFT)],
                [101, 113, str_pad('10000', 13, '0', STR_PAD_LEFT)],
                [114, 126, str_pad('12000', 13, '0', STR_PAD_LEFT)],
                [127, 139, str_pad('5000', 13, '0', STR_PAD_LEFT)],
                [148, 160, str_pad('2000', 13, '0', STR_PAD_LEFT)],
            ]),
            $this->buildLine('21', [
                [88, 100, str_pad('50000', 13, '0', STR_PAD_LEFT)],
                [101, 113, str_pad('5000', 13, '0', STR_PAD_LEFT)],
                [114, 126, str_pad('6000', 13, '0', STR_PAD_LEFT)],
                [127, 139, str_pad('2500', 13, '0', STR_PAD_LEFT)],
                [148, 160, str_pad('1000', 13, '0', STR_PAD_LEFT)],
            ]),
            $this->buildLine('25', [
                [14, 24, 'DEPENDENTE1'],
            ]),
            $this->buildLine('26', [
                [14, 15, '26'],
                [106, 118, str_pad('10000', 13, '0', STR_PAD_LEFT)],
                [119, 131, str_pad('0', 13, '0', STR_PAD_LEFT)],
            ]),
            $this->buildLine('26', [
                [14, 15, '80'],
                [106, 118, str_pad('20000', 13, '0', STR_PAD_LEFT)],
                [119, 131, str_pad('0', 13, '0', STR_PAD_LEFT)],
            ]),
            $this->buildLine('27', [
                [532, 544, str_pad('0', 13, '0', STR_PAD_LEFT)],
                [545, 557, str_pad('1000', 13, '0', STR_PAD_LEFT)],
                [1101, 1102, '01'],
            ]),
            $this->buildLine('27', [
                [532, 544, str_pad('0', 13, '0', STR_PAD_LEFT)],
                [545, 557, str_pad('0', 13, '0', STR_PAD_LEFT)],
                [1101, 1102, '02'],
            ]),
            $this->buildLine('28', [
                [528, 540, str_pad('1000', 13, '0', STR_PAD_LEFT)],
                [541, 553, str_pad('2000', 13, '0', STR_PAD_LEFT)],
            ]),
            $this->buildLine('45', [
                [90, 102, str_pad('1000', 13, '0', STR_PAD_LEFT)],
            ]),
            $this->buildLine('49', [
                [14, 26, str_pad('100', 13, '0', STR_PAD_LEFT)],
            ]),
            $this->buildLine('23', [
                [14, 17, '1501'],
                [18, 30, str_pad('5000', 13, '0', STR_PAD_LEFT)],
            ]),
            $this->buildLine('24', [
                [14, 17, '0006'],
                [18, 30, str_pad('2500', 13, '0', STR_PAD_LEFT)],
            ]),
            $this->buildLine('40', [
                [14, 15, '01'],
                [16, 28, str_pad('10000', 13, '0', STR_PAD_LEFT)],
                [55, 67, str_pad('5000', 13, '0', STR_PAD_LEFT)],
            ]),
            $this->buildLine('50', [
                [14, 26, str_pad('5000', 13, '0', STR_PAD_LEFT)],
            ]),
        ];

        $data = $this->service->parse($this->writeTempDec($lines));

        $this->assertSame(42, $data->complexityScore);
        $this->assertSame('alta', $data->complexityLevel);
        $byKey = collect($data->complexityBreakdown)->keyBy('key');
        $this->assertSame(2, $byKey['fontes_pagadoras']['points']);
        $this->assertSame(20, $byKey['renda_variavel']['points']);
        $this->assertSame(10, $byKey['atividade_rural']['points']);
    }

    public function test_classifies_complexity_as_media_on_threshold(): void
    {
        $lines = [
            $this->headerLine(),
            $this->buildLine('21', [[88, 100, str_pad('1000', 13, '0', STR_PAD_LEFT)]]),
            $this->buildLine('21', [[88, 100, str_pad('2000', 13, '0', STR_PAD_LEFT)]]),
            $this->buildLine('25', [[14, 24, 'DEP1']]),
            $this->buildLine('25', [[14, 24, 'DEP2']]),
            $this->buildLine('26', [[14, 15, '26'], [106, 118, str_pad('1000', 13, '0', STR_PAD_LEFT)]]),
            $this->buildLine('26', [[14, 15, '10'], [106, 118, str_pad('2000', 13, '0', STR_PAD_LEFT)]]),
            $this->buildLine('27', [[545, 557, str_pad('1000', 13, '0', STR_PAD_LEFT)]]),
            $this->buildLine('28', [[541, 553, str_pad('1000', 13, '0', STR_PAD_LEFT)]]),
            $this->buildLine('45', [[90, 102, str_pad('1000', 13, '0', STR_PAD_LEFT)]]),
            $this->buildLine('23', [[14, 17, '1501'], [18, 30, str_pad('500', 13, '0', STR_PAD_LEFT)]]),
        ];

        $data = $this->service->parse($this->writeTempDec($lines));

        $this->assertSame(10, $data->complexityScore);
        $this->assertSame('media', $data->complexityLevel);
    }

    private function headerLine(
        int $exercicio = 2024,
        int $anoBase = 2023,
        string $cpf = '12345678901',
        string $nome = 'Maria Teste',
        bool $completa = true,
        bool $retificadora = false,
        string $recibo = ''
    ): string {
        return $this->buildLine('IRPF', [
            [9, 12, (string) $exercicio],
            [13, 16, (string) $anoBase],
            [22, 32, $cpf],
            [40, 99, $nome],
            [121, 121, $completa ? 'S' : 'N'],
            [21, 21, $retificadora ? '1' : '0'],
            [124, 133, $recibo],
        ]);
    }

    private function buildLine(string $prefix, array $fields): string
    {
        $line = str_pad($prefix, 1200, ' ');

        foreach ($fields as [$start, $end, $value]) {
            $length = $end - $start + 1;
            $line = substr_replace($line, str_pad((string) $value, $length, ' ', STR_PAD_RIGHT), $start - 1, $length);
        }

        return $line;
    }

    private function writeTempDec(array $lines): string
    {
        $path = tempnam(sys_get_temp_dir(), 'dec');
        file_put_contents($path, implode(PHP_EOL, $lines));
        $this->tempFiles[] = $path;

        return $path;
    }
}

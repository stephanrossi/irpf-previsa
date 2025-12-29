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
    }

    public function test_parses_money_with_leading_zeros(): void
    {
        $lines = [
            $this->headerLine(),
            $this->buildLine('20', [
                [66, 78, str_pad('12345', 13, '0', STR_PAD_LEFT)],
                [470, 482, str_pad('300', 13, '0', STR_PAD_LEFT)],
                [444, 456, str_pad('5678', 13, '0', STR_PAD_LEFT)],
            ]),
        ];

        $data = $this->service->parse($this->writeTempDec($lines));

        $this->assertEqualsWithDelta(123.45, $data->totalRendTributaveis, 0.001);
        $this->assertEqualsWithDelta(56.78, $data->totalDividasOnus, 0.001);
        $this->assertEqualsWithDelta(3.00, $data->totalRendaIsenta, 0.001);
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
            $this->headerLine(),
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
    }

    private function headerLine(
        int $exercicio = 2024,
        int $anoBase = 2023,
        string $cpf = '12345678901',
        string $nome = 'Maria Teste',
        bool $completa = true
    ): string {
        return $this->buildLine('IRPF', [
            [9, 12, (string) $exercicio],
            [13, 16, (string) $anoBase],
            [22, 32, $cpf],
            [40, 99, $nome],
            [121, 121, $completa ? 'S' : 'N'],
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

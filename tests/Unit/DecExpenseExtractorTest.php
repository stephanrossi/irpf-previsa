<?php

namespace Tests\Unit;

use App\Services\DecExpenseExtractor;
use PHPUnit\Framework\TestCase;

class DecExpenseExtractorTest extends TestCase
{
    private function buildLine(string $prefix, array $fields): string
    {
        $line = str_pad($prefix, 1200, ' ');
        foreach ($fields as [$start, $end, $value]) {
            $length = $end - $start + 1;
            $line = substr_replace($line, str_pad((string) $value, $length, ' ', STR_PAD_RIGHT), $start - 1, $length);
        }
        return $line;
    }

    public function test_aggregates_by_category_with_reduction(): void
    {
        $service = new DecExpenseExtractor();
        $line1 = $this->buildLine('26', [
            [14, 15, '26'],
            [106, 118, str_pad('10000', 13, '0', STR_PAD_LEFT)], // 100.00
            [119, 131, str_pad('2000', 13, '0', STR_PAD_LEFT)],  // 20.00
        ]);
        $line2 = $this->buildLine('26', [
            [14, 15, '26'],
            [106, 118, str_pad('5000', 13, '0', STR_PAD_LEFT)], // 50.00
            [119, 131, str_pad('0', 13, '0', STR_PAD_LEFT)],
        ]);
        $service->addPaymentLine($line1);
        $service->addPaymentLine($line2);
        $service->setIrPago(10.00);

        $result = $service->result();

        $this->assertEqualsWithDelta(140.00, $result['gastos_declarados_total'], 0.001); // (80+50) + 10 IR
        $this->assertEqualsWithDelta(130.00, $result['total_planos_saude'], 0.001);
        $this->assertEqualsWithDelta(10.00, $result['total_ir_pago'], 0.001);
        $this->assertSame(2, $result['gastos_declarados_breakdown']['planos_saude']['itens']);
    }

    public function test_category_mapping_pgbl_and_medicas(): void
    {
        $service = new DecExpenseExtractor();
        $pgblLine = $this->buildLine('26', [
            [14, 15, '36'],
            [106, 118, str_pad('1000', 13, '0', STR_PAD_LEFT)], // 10.00
            [119, 131, str_pad('0', 13, '0', STR_PAD_LEFT)],
        ]);
        $medLine = $this->buildLine('26', [
            [14, 15, '10'],
            [106, 118, str_pad('2000', 13, '0', STR_PAD_LEFT)], // 20.00
            [119, 131, str_pad('0', 13, '0', STR_PAD_LEFT)],
        ]);
        $service->addPaymentLine($pgblLine);
        $service->addPaymentLine($medLine);

        $result = $service->result();

        $this->assertEqualsWithDelta(10.00, $result['total_pgbl'], 0.001);
        $this->assertEqualsWithDelta(20.00, $result['total_despesas_medicas_odont'], 0.001);
    }
}

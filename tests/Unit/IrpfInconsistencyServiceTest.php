<?php

namespace Tests\Unit;

use App\Models\Declaration;
use App\Services\IrpfInconsistencyService;
use PHPUnit\Framework\TestCase;

class IrpfInconsistencyServiceTest extends TestCase
{
    public function test_calculates_ok_when_variation_not_positive(): void
    {
        $service = new IrpfInconsistencyService();
        $declaration = new Declaration([
            'total_rend_tributaveis' => 1000,
            'total_renda_isenta' => 500,
            'total_bens_adquiridos_ano' => 100,
            'gastos_declarados_total' => 0,
        ]);

        $result = $service->calculate($declaration, 0);

        $this->assertFalse($result->risco);
        $this->assertEqualsWithDelta(0, $result->variacaoDescoberto, 0.001);
    }

    public function test_calculates_risk_when_variation_positive(): void
    {
        $service = new IrpfInconsistencyService();
        $declaration = new Declaration([
            'total_rend_tributaveis' => 100,
            'total_renda_isenta' => 50,
            'total_bens_adquiridos_ano' => 500,
            'gastos_declarados_total' => 0,
        ]);

        $result = $service->calculate($declaration, 0);

        $this->assertTrue($result->risco);
        $this->assertEqualsWithDelta(350, $result->variacaoDescoberto, 0.001);
    }

    public function test_defaults_gastos_estimados_to_zero_when_null(): void
    {
        $service = new IrpfInconsistencyService();
        $declaration = new Declaration([
            'total_rend_tributaveis' => 200,
            'total_renda_isenta' => 0,
            'total_bens_adquiridos_ano' => 50,
            'gastos_estimados' => null,
            'gastos_declarados_total' => 0,
        ]);

        $result = $service->calculate($declaration);

        $this->assertFalse($result->risco);
        $this->assertEqualsWithDelta(0, $result->variacaoDescoberto, 0.001);
    }

    public function test_uses_gastos_declarados_in_variation(): void
    {
        $service = new IrpfInconsistencyService();
        $declaration = new Declaration([
            'total_rend_tributaveis' => 100,
            'total_renda_isenta' => 50,
            'total_bens_adquiridos_ano' => 120,
            'gastos_estimados' => 0,
            'gastos_declarados_total' => 80,
        ]);

        $result = $service->calculate($declaration);

        // renda total 150 - gastos (80) = 70; bens 120 - 70 = 50 (risco)
        $this->assertTrue($result->risco);
        $this->assertEqualsWithDelta(50, $result->variacaoDescoberto, 0.001);
    }
}

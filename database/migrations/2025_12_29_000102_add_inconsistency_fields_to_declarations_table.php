<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('declarations', function (Blueprint $table) {
            $table->decimal('total_renda_isenta', 15, 2)->default(0)->after('total_rend_tributaveis');
            $table->decimal('gastos_estimados', 15, 2)->nullable()->after('total_bens_adquiridos_ano');
            $table->decimal('variacao_patrimonial_descoberto', 15, 2)->default(0)->after('gastos_estimados');
            $table->boolean('risco_variacao_patrimonial')->default(false)->after('variacao_patrimonial_descoberto');
            $table->json('inconsistencia_payload')->nullable()->after('risco_variacao_patrimonial');
        });
    }

    public function down(): void
    {
        Schema::table('declarations', function (Blueprint $table) {
            $table->dropColumn([
                'total_renda_isenta',
                'gastos_estimados',
                'variacao_patrimonial_descoberto',
                'risco_variacao_patrimonial',
                'inconsistencia_payload',
            ]);
        });
    }
};

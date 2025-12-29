<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('declarations', function (Blueprint $table) {
            $table->decimal('total_planos_saude', 15, 2)->default(0)->after('total_renda_isenta');
            $table->decimal('total_despesas_medicas_odont', 15, 2)->default(0)->after('total_planos_saude');
            $table->decimal('total_despesas_instrucao', 15, 2)->default(0)->after('total_despesas_medicas_odont');
            $table->decimal('total_pensao_judicial', 15, 2)->default(0)->after('total_despesas_instrucao');
            $table->decimal('total_pgbl', 15, 2)->default(0)->after('total_pensao_judicial');
            $table->decimal('total_ir_pago', 15, 2)->default(0)->after('total_pgbl');
            $table->decimal('gastos_declarados_total', 15, 2)->default(0)->after('total_ir_pago');
            $table->json('gastos_declarados_breakdown')->nullable()->after('gastos_declarados_total');
        });
    }

    public function down(): void
    {
        Schema::table('declarations', function (Blueprint $table) {
            $table->dropColumn([
                'total_planos_saude',
                'total_despesas_medicas_odont',
                'total_despesas_instrucao',
                'total_pensao_judicial',
                'total_pgbl',
                'total_ir_pago',
                'gastos_declarados_total',
                'gastos_declarados_breakdown',
            ]);
        });
    }
};

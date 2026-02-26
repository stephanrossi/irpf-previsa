<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('declarations', function (Blueprint $table) {
            $table->decimal('total_atividade_rural_resultado_tributavel', 15, 2)->default(0)->after('total_doacoes_partidos_politicos');
        });
    }

    public function down(): void
    {
        Schema::table('declarations', function (Blueprint $table) {
            $table->dropColumn('total_atividade_rural_resultado_tributavel');
        });
    }
};

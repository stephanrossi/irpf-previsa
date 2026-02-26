<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('declarations', function (Blueprint $table) {
            $table->decimal('total_dividas_ano_anterior', 15, 2)->default(0)->after('total_dividas_onus');
            $table->decimal('total_dividas_ano_atual', 15, 2)->default(0)->after('total_dividas_ano_anterior');
            $table->decimal('total_dividas_onus_reais', 15, 2)->default(0)->after('total_dividas_ano_atual');
        });
    }

    public function down(): void
    {
        Schema::table('declarations', function (Blueprint $table) {
            $table->dropColumn([
                'total_dividas_ano_anterior',
                'total_dividas_ano_atual',
                'total_dividas_onus_reais',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('declarations', function (Blueprint $table) {
            $table->decimal('total_bens_ano_anterior', 15, 2)->default(0)->after('total_bens_imoveis');
            $table->decimal('total_bens_ano_atual', 15, 2)->default(0)->after('total_bens_ano_anterior');
            $table->decimal('total_bens_reais', 15, 2)->default(0)->after('total_bens_ano_atual');
        });
    }

    public function down(): void
    {
        Schema::table('declarations', function (Blueprint $table) {
            $table->dropColumn([
                'total_bens_ano_anterior',
                'total_bens_ano_atual',
                'total_bens_reais',
            ]);
        });
    }
};

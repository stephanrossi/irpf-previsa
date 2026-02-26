<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('declarations', function (Blueprint $table) {
            $table->decimal('rend_trib_pj', 15, 2)->default(0)->after('total_rend_tributaveis');
            $table->decimal('rend_trib_pf_exterior', 15, 2)->default(0)->after('rend_trib_pj');
        });
    }

    public function down(): void
    {
        Schema::table('declarations', function (Blueprint $table) {
            $table->dropColumn(['rend_trib_pj', 'rend_trib_pf_exterior']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('declarations', function (Blueprint $table) {
            $table->decimal('total_doacoes_efetuadas', 15, 2)->default(0)->after('total_pagamentos_efetuados');
        });
    }

    public function down(): void
    {
        Schema::table('declarations', function (Blueprint $table) {
            $table->dropColumn('total_doacoes_efetuadas');
        });
    }
};

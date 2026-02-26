<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('declarations', function (Blueprint $table) {
            $table->unsignedInteger('complexity_score')->nullable()->after('inconsistencia_payload');
            $table->enum('complexity_level', ['baixa', 'media', 'alta'])->nullable()->after('complexity_score');
        });
    }

    public function down(): void
    {
        Schema::table('declarations', function (Blueprint $table) {
            $table->dropColumn(['complexity_score', 'complexity_level']);
        });
    }
};

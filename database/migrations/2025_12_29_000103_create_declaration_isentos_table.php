<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('declaration_isentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('declaration_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('cod_isento');
            $table->decimal('valor', 15, 2);
            $table->timestamps();

            $table->index('cod_isento');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('declaration_isentos');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('declarations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('exercicio');
            $table->unsignedInteger('ano_base');
            $table->enum('tipo', ['completa', 'simplificada']);
            $table->decimal('total_rend_tributaveis', 15, 2)->default(0);
            $table->decimal('total_bens_imoveis', 15, 2)->default(0);
            $table->decimal('total_dividas_onus', 15, 2)->default(0);
            $table->decimal('total_bens_adquiridos_ano', 15, 2)->default(0);
            $table->string('source_file_path');
            $table->timestamp('imported_at');
            $table->timestamps();

            $table->unique(['client_id', 'ano_base']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('declarations');
    }
};

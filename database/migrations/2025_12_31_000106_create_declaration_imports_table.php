<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('declaration_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('declaration_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_retificadora')->default(false);
            $table->string('recibo_anterior', 10)->nullable();
            $table->string('source_file_path');
            $table->string('source_sha256', 64);
            $table->timestamp('imported_at');
            $table->timestamps();

            $table->unique(['declaration_id', 'source_sha256']);
            $table->index(['declaration_id', 'imported_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('declaration_imports');
    }
};

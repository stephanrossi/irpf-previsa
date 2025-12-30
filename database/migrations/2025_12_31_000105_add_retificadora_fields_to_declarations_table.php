<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('declarations', function (Blueprint $table) {
            $table->boolean('last_is_retificadora')->default(false)->after('source_file_path');
            $table->string('last_recibo_anterior', 10)->nullable()->after('last_is_retificadora');
            $table->string('last_source_sha256', 64)->nullable()->after('last_recibo_anterior');
            $table->timestamp('last_imported_at')->nullable()->after('last_source_sha256');
        });
    }

    public function down(): void
    {
        Schema::table('declarations', function (Blueprint $table) {
            $table->dropColumn([
                'last_is_retificadora',
                'last_recibo_anterior',
                'last_source_sha256',
                'last_imported_at',
            ]);
        });
    }
};

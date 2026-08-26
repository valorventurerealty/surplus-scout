<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_surplus_csv_import_rows', function (Blueprint $table): void {
            $table->string('mailing_country', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ai_surplus_csv_import_rows', function (Blueprint $table): void {
            $table->string('mailing_country', 2)->nullable()->change();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surplus_cases', function (Blueprint $table): void {
            $table->string('tax_deed_number', 120)->nullable()->after('normalized_parcel_id')->index();
        });

        Schema::table('ai_surplus_csv_import_rows', function (Blueprint $table): void {
            $table->date('sale_date')->nullable()->after('surplus_amount');
            $table->string('tax_deed_number', 120)->nullable()->after('sale_date');
            $table->string('certificate_number', 120)->nullable()->after('tax_deed_number');
        });
    }

    public function down(): void
    {
        Schema::table('ai_surplus_csv_import_rows', function (Blueprint $table): void {
            $table->dropColumn(['sale_date', 'tax_deed_number', 'certificate_number']);
        });
        Schema::table('surplus_cases', function (Blueprint $table): void {
            $table->dropIndex(['tax_deed_number']);
            $table->dropColumn('tax_deed_number');
        });
    }
};

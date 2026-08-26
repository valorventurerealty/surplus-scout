<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_surplus_csv_import_rows', function (Blueprint $table): void {
            $table->string('owner_phone', 40)->nullable()->after('mailing_postal_code');
            $table->string('owner_email', 255)->nullable()->after('owner_phone');
            $table->json('related_contacts_json')->nullable()->after('certificate_number');
        });
    }

    public function down(): void
    {
        Schema::table('ai_surplus_csv_import_rows', function (Blueprint $table): void {
            $table->dropColumn(['owner_phone', 'owner_email', 'related_contacts_json']);
        });
    }
};

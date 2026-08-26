<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table): void {
            $table->string('closing_documents_url', 2048)->nullable()->after('document_drive_url');
        });

        DB::table('property_checklist_items')->where('item_key', 'paid_receipt')->delete();
        DB::table('properties')->whereIn('status', ['active', 'under_contract'])->update([
            'status' => 'actively_working',
        ]);
    }

    public function down(): void
    {
        DB::table('properties')->where('status', 'actively_working')->update(['status' => 'active']);

        Schema::table('properties', function (Blueprint $table): void {
            $table->dropColumn('closing_documents_url');
        });
    }
};

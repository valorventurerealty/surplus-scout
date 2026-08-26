<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_surplus_case', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('surplus_case_id')->constrained('surplus_cases')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->string('role', 40)->default('relative')->index();
            $table->string('relationship_notes', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['surplus_case_id', 'contact_id']);
        });

        DB::table('surplus_cases')->whereNotNull('claimant_contact_id')->orderBy('id')->chunkById(200, function ($cases): void {
            $now = now();
            $rows = $cases->map(fn ($case): array => [
                'surplus_case_id' => $case->id,
                'contact_id' => $case->claimant_contact_id,
                'role' => 'claimant',
                'created_by' => $case->created_by,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();
            DB::table('contact_surplus_case')->insertOrIgnore($rows);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_surplus_case');
    }
};

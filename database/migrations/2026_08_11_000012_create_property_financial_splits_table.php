<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_financial_splits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('vvr_percentage', 5, 2)->default(20);
            $table->foreignId('contact_one_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->decimal('contact_one_percentage', 5, 2)->default(40);
            $table->foreignId('contact_two_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->decimal('contact_two_percentage', 5, 2)->default(40);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_financial_splits');
    }
};

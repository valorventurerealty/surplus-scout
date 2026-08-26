<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('negotiation_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 180);
            $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->foreignId('buyer_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('status', 30)->default('draft')->index();
            $table->decimal('asking_price', 14, 2);
            $table->decimal('all_in_amount', 14, 2);
            $table->decimal('buyer_offer', 14, 2)->nullable();
            $table->decimal('vvr_percentage', 5, 2)->default(20);
            $table->decimal('investor_one_percentage', 5, 2)->default(40);
            $table->decimal('investor_two_percentage', 5, 2)->default(40);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['property_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('negotiation_plans');
    }
};

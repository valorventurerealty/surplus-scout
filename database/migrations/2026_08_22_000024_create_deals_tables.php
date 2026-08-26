<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('token')->unique();
            $table->string('deal_number', 30)->nullable()->unique();
            $table->string('title', 180);
            $table->string('type', 40)->index();
            $table->string('status', 40)->default('draft')->index();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('primary_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 120)->nullable();
            $table->date('contract_date')->nullable();
            $table->date('due_diligence_deadline')->nullable();
            $table->date('projected_close_date')->nullable();
            $table->date('actual_close_date')->nullable();
            $table->decimal('offer_amount', 14, 2)->nullable();
            $table->decimal('contract_amount', 14, 2)->nullable();
            $table->decimal('earnest_money', 14, 2)->nullable();
            $table->decimal('projected_revenue', 14, 2)->nullable();
            $table->decimal('actual_revenue', 14, 2)->nullable();
            $table->string('document_drive_url', 2048)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['property_id', 'type', 'status']);
        });

        Schema::create('contact_deal', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('role', 40);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['deal_id', 'contact_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_deal');
        Schema::dropIfExists('deals');
    }
};

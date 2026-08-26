<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_interactions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('token')->unique();
            $table->string('provider', 40)->default('beside')->index();
            $table->string('provider_event_id', 191);
            $table->string('event_type', 30)->index();
            $table->string('direction', 20)->default('unknown')->index();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('match_status', 30)->default('unmatched')->index();
            $table->string('caller_phone', 50)->nullable();
            $table->string('normalized_phone', 40)->nullable()->index();
            $table->string('caller_name', 180)->nullable();
            $table->string('caller_email')->nullable();
            $table->string('caller_company')->nullable();
            $table->string('inbox', 180)->nullable();
            $table->timestamp('occurred_at')->index();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->text('summary')->nullable();
            $table->longText('transcript')->nullable();
            $table->text('recording_url')->nullable();
            $table->json('action_items')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamps();

            $table->unique(['provider', 'provider_event_id'], 'phone_interactions_provider_event_unique');
            $table->index(['contact_id', 'occurred_at'], 'phone_interactions_contact_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_interactions');
    }
};

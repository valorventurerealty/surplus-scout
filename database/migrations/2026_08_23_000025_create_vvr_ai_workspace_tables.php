<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('intent', 80)->default('create_property_from_documents')->index();
            $table->string('status', 30)->default('processing')->index();
            $table->json('result_json')->nullable();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'last_message_at']);
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role', 20);
            $table->longText('content');
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });

        Schema::table('property_intake_files', function (Blueprint $table) {
            $table->foreignId('ai_conversation_id')->nullable()->after('user_id')->constrained('ai_conversations')->nullOnDelete();
            $table->string('source_mode', 20)->default('document')->after('status');
            $table->text('user_prompt')->nullable()->after('source_mode');
            $table->char('request_fingerprint', 64)->nullable()->after('sha256')->index();
        });
    }

    public function down(): void
    {
        Schema::table('property_intake_files', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ai_conversation_id');
            $table->dropIndex(['request_fingerprint']);
            $table->dropColumn(['source_mode', 'user_prompt', 'request_fingerprint']);
        });

        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};

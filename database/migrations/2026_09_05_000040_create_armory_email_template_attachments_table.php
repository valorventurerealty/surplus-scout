<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('armory_email_template_attachments')) {
            Schema::create('armory_email_template_attachments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('token');
                $table->unsignedBigInteger('armory_email_template_id');
                $table->string('disk', 30)->default('local');
                $table->string('path', 500);
                $table->string('original_name');
                $table->string('mime_type', 150)->nullable();
                $table->unsignedBigInteger('size_bytes');
                $table->char('sha256', 64);
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->timestamps();
            });
        }

        if (! $this->indexOnColumnExists('armory_email_template_attachments', 'token', true)) {
            Schema::table('armory_email_template_attachments', fn (Blueprint $table) => $table->unique('token', 'aeta_token_unique'));
        }

        if (! $this->indexOnColumnExists('armory_email_template_attachments', 'sha256')) {
            Schema::table('armory_email_template_attachments', fn (Blueprint $table) => $table->index('sha256', 'aeta_sha256_idx'));
        }

        if (! $this->foreignKeyExists('armory_email_template_attachments', 'aeta_template_fk')) {
            Schema::table('armory_email_template_attachments', function (Blueprint $table): void {
                $table->foreign('armory_email_template_id', 'aeta_template_fk')->references('id')->on('armory_email_templates')->cascadeOnDelete();
            });
        }

        if (! $this->foreignKeyExists('armory_email_template_attachments', 'aeta_uploaded_by_fk')) {
            Schema::table('armory_email_template_attachments', function (Blueprint $table): void {
                $table->foreign('uploaded_by', 'aeta_uploaded_by_fk')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('outbound_email_attachments', 'armory_email_template_attachment_id')) {
            Schema::table('outbound_email_attachments', function (Blueprint $table): void {
                $table->unsignedBigInteger('armory_email_template_attachment_id')->nullable()->after('outbound_email_id');
            });
        }

        if (! $this->foreignKeyExists('outbound_email_attachments', 'oea_template_attachment_fk')) {
            Schema::table('outbound_email_attachments', function (Blueprint $table): void {
                $table->foreign('armory_email_template_attachment_id', 'oea_template_attachment_fk')->references('id')->on('armory_email_template_attachments')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('outbound_email_attachments', 'armory_email_template_attachment_id')) {
            if ($this->foreignKeyExists('outbound_email_attachments', 'oea_template_attachment_fk')) {
                Schema::table('outbound_email_attachments', fn (Blueprint $table) => $table->dropForeign('oea_template_attachment_fk'));
            }
            Schema::table('outbound_email_attachments', fn (Blueprint $table) => $table->dropColumn('armory_email_template_attachment_id'));
        }

        Schema::dropIfExists('armory_email_template_attachments');
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return false;
        }

        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }

    private function indexOnColumnExists(string $table, string $column, bool $unique = false): bool
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return false;
        }

        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->when($unique, fn ($query) => $query->where('NON_UNIQUE', 0))
            ->exists();
    }
};

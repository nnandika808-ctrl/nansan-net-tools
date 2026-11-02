<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recovery_codes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->char('code_hash', 64)->unique();
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('password_recovery_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('status', 24)->default('pending')->index();
            $t->text('verification_notes')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('user_preferences', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->json('preferences');
            $t->timestamps();
        });
        foreach (['audit_logs', 'user_activity_logs'] as $name) {
            Schema::create($name, function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $t->string('action', 80)->index();
                $t->string('subject_type', 80)->nullable();
                $t->unsignedBigInteger('subject_id')->nullable();
                $t->json('metadata')->nullable();
                $t->timestamp('created_at')->useCurrent()->index();
                $t->index(['subject_type', 'subject_id']);
            });
        }
        Schema::create('tool_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name', 100);
            $t->string('slug', 120)->unique();
            $t->text('description')->nullable();
            $t->timestamps();
        });
        Schema::create('tools', function (Blueprint $t) {
            $t->id();
            $t->string('generator_id', 8)->unique();
            $t->foreignId('tool_category_id')->constrained()->restrictOnDelete();
            $t->string('slug', 120)->unique();
            $t->string('name', 150);
            $t->text('description');
            $t->string('icon', 32);
            $t->string('status', 32)->default('BLOCKED')->index();
            $t->text('blocked_reason')->nullable();
            $t->json('ros_versions');
            $t->boolean('featured')->default(false);
            $t->unsignedInteger('sort_order')->default(0);
            $t->unsignedBigInteger('usage_count')->default(0);
            $t->timestamps();
        });
        Schema::create('tool_tags', function (Blueprint $t) {
            $t->id();
            $t->string('name', 64)->unique();
            $t->timestamps();
        });
        Schema::create('tool_tag_relations', function (Blueprint $t) {
            $t->foreignId('tool_id')->constrained()->cascadeOnDelete();
            $t->foreignId('tool_tag_id')->constrained()->cascadeOnDelete();
            $t->primary(['tool_id', 'tool_tag_id']);
        });
        Schema::create('tool_synonyms', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tool_id')->constrained()->cascadeOnDelete();
            $t->string('synonym', 100)->index();
            $t->unique(['tool_id', 'synonym']);
        });
        Schema::create('generator_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tool_id')->constrained()->cascadeOnDelete();
            $t->string('version', 32);
            $t->string('status', 32)->default('BLOCKED');
            $t->text('changelog');
            $t->json('evidence')->nullable();
            $t->timestamps();
            $t->unique(['tool_id', 'version']);
        });
        Schema::create('generator_form_definitions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('generator_version_id')->unique()->constrained()->cascadeOnDelete();
            $t->json('schema');
            $t->timestamps();
        });
        Schema::create('generator_templates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tool_id')->constrained()->cascadeOnDelete();
            $t->string('name', 100);
            $t->string('version', 32);
            $t->json('defaults');
            $t->boolean('is_active')->default(false);
            $t->timestamps();
        });
        Schema::create('user_presets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('tool_id')->constrained()->restrictOnDelete();
            $t->string('name', 100);
            $t->json('input');
            $t->string('generator_version', 32);
            $t->timestamps();
            $t->index(['user_id', 'updated_at']);
        });
        Schema::create('script_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('tool_id')->constrained()->restrictOnDelete();
            $t->json('input');
            $t->longText('output');
            $t->string('generator_version', 32);
            $t->string('ros_version', 8);
            $t->char('output_sha256', 64);
            $t->timestamps();
            $t->index(['user_id', 'created_at']);
        });
        Schema::create('favorites', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('tool_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['user_id', 'tool_id']);
        });
        Schema::create('documentation_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name', 100);
            $t->string('slug', 120)->unique();
            $t->timestamps();
        });
        Schema::create('documentation_articles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('documentation_category_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('tool_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('title', 180);
            $t->string('slug', 180)->unique();
            $t->text('excerpt')->nullable();
            $t->longText('body');
            $t->string('status', 16)->default('draft')->index();
            $t->timestamp('published_at')->nullable()->index();
            $t->timestamps();
        });
        Schema::create('announcements', function (Blueprint $t) {
            $t->id();
            $t->string('title', 180);
            $t->text('body');
            $t->boolean('banner')->default(false);
            $t->timestamp('published_at')->nullable()->index();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });
        Schema::create('announcement_reads', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $t->timestamp('read_at')->useCurrent();
            $t->unique(['user_id', 'announcement_id']);
        });
        Schema::create('feedback_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type', 16);
            $t->string('title', 180);
            $t->text('body');
            $t->string('status', 24)->default('open')->index();
            $t->text('admin_reply')->nullable();
            $t->timestamps();
        });
        Schema::create('media_files', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('disk', 24)->default('local');
            $t->string('path')->unique();
            $t->string('original_name', 180);
            $t->string('mime_type', 80);
            $t->unsignedBigInteger('size');
            $t->string('folder', 80)->default('general');
            $t->boolean('is_public')->default(false)->index();
            $t->timestamps();
        });
        Schema::create('feedback_attachments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('feedback_report_id')->constrained()->cascadeOnDelete();
            $t->foreignId('media_file_id')->constrained()->cascadeOnDelete();
        });
        Schema::create('cms_pages', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 120)->unique();
            $t->string('title', 180);
            $t->longText('body');
            $t->string('status', 16)->default('draft')->index();
            $t->boolean('owner_review_required')->default(false);
            $t->timestamps();
        });
        Schema::create('site_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key', 80)->unique();
            $t->json('value');
            $t->timestamps();
        });
        Schema::create('seo_metadata', function (Blueprint $t) {
            $t->id();
            $t->string('path', 200)->unique();
            $t->string('title', 180);
            $t->text('description')->nullable();
            $t->string('og_image')->nullable();
            $t->boolean('noindex')->default(false);
            $t->timestamps();
        });
        Schema::create('backup_records', function (Blueprint $t) {
            $t->id();
            $t->string('status', 32)->index();
            $t->string('disk', 24);
            $t->string('path')->nullable();
            $t->unsignedBigInteger('size')->nullable();
            $t->char('sha256', 64)->nullable();
            $t->timestamp('restore_tested_at')->nullable();
            $t->text('message')->nullable();
            $t->timestamps();
        });
        Schema::create('deletion_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->timestamp('requested_at');
            $t->timestamp('delete_after')->index();
            $t->timestamp('cancelled_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['deletion_requests', 'backup_records', 'seo_metadata', 'site_settings', 'cms_pages', 'feedback_attachments', 'media_files', 'feedback_reports', 'announcement_reads', 'announcements', 'documentation_articles', 'documentation_categories', 'favorites', 'script_histories', 'user_presets', 'generator_templates', 'generator_form_definitions', 'generator_versions', 'tool_synonyms', 'tool_tag_relations', 'tool_tags', 'tools', 'tool_categories', 'user_activity_logs', 'audit_logs', 'user_preferences', 'password_recovery_requests', 'recovery_codes'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

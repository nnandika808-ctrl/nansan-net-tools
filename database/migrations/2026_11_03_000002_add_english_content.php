<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['documentation_articles', 'cms_pages', 'announcements'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('title_en', 180)->nullable();
                $t->longText('body_en')->nullable();
            });
        }
        Schema::table('documentation_articles', fn (Blueprint $t) => $t->string('excerpt_en', 500)->nullable());
        Schema::table('tools', fn (Blueprint $t) => $t->text('description_en')->nullable());
    }

    public function down(): void
    {
        Schema::table('tools', fn (Blueprint $t) => $t->dropColumn('description_en'));
        Schema::table('documentation_articles', fn (Blueprint $t) => $t->dropColumn('excerpt_en'));
        foreach (['documentation_articles', 'cms_pages', 'announcements'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn(['title_en', 'body_en']));
        }
    }
};

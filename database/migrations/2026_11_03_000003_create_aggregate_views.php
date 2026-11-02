<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_view_daily', function (Blueprint $t) {
            $t->id();
            $t->date('day')->index();
            $t->string('path', 200);
            $t->unsignedBigInteger('views')->default(0);
            $t->unique(['day', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_view_daily');
    }
};

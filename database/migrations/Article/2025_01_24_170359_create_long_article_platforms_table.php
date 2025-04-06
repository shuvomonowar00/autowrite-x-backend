<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('long_article_platforms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('long_article_id')
                ->constrained('long_articles')
                ->onDelete('cascade');
            $table->foreignId('post_platform_id')
                ->constrained('post_platforms')
                ->onDelete('cascade');
            $table->string('post_url');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('long_article_platforms');
    }
};

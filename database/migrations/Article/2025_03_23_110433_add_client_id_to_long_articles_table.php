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
        Schema::table('long_articles', function (Blueprint $table) {
            // Add nullable client_id column first
            $table->unsignedBigInteger('client_id')->nullable()->after('id');

            // Add foreign key constraint
            $table->foreign('client_id')
                ->references('id')
                ->on('clients')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('long_articles', function (Blueprint $table) {
            // Drop foreign key constraint first
            $table->dropForeign(['client_id']);

            // Then drop the column
            $table->dropColumn('client_id');
        });
    }
};

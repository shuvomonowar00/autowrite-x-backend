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
        Schema::table('clients', function (Blueprint $table) {
            $table->string('google_id')->nullable()->after('password');
            $table->text('google_avatar')->nullable()->after('profile_photo');

            // If email_verified_at doesn't already exist
            if (!Schema::hasColumn('clients', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable()->after('email');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('google_id');
            $table->dropColumn('google_avatar');

            // Only drop if we created it
            if (!Schema::hasColumn('clients', 'verification_token')) {
                $table->dropColumn('email_verified_at');
            }
        });
    }
};

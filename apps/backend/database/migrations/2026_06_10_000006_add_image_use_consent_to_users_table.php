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
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'image_use_consent_at')) {
                $table->timestamp('image_use_consent_at')->nullable()->after('is_admin');
            }

            if (! Schema::hasColumn('users', 'image_use_consent_version')) {
                $table->string('image_use_consent_version')->nullable()->after('image_use_consent_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'image_use_consent_version')) {
                $table->dropColumn('image_use_consent_version');
            }

            if (Schema::hasColumn('users', 'image_use_consent_at')) {
                $table->dropColumn('image_use_consent_at');
            }
        });
    }
};

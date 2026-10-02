<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('download_unlocks', function (Blueprint $table): void {
            if (! Schema::hasColumn('download_unlocks', 'package_code')) {
                $table->string('package_code', 32)->default('original')->after('customer_email');
            }

            if (! Schema::hasColumn('download_unlocks', 'download_variant')) {
                $table->string('download_variant', 32)->default('original')->after('package_code');
            }

            if (! Schema::hasColumn('download_unlocks', 'price_cents')) {
                $table->integer('price_cents')->default(0)->after('download_variant');
            }
        });
    }

    public function down(): void
    {
        Schema::table('download_unlocks', function (Blueprint $table): void {
            if (Schema::hasColumn('download_unlocks', 'price_cents')) {
                $table->dropColumn('price_cents');
            }

            if (Schema::hasColumn('download_unlocks', 'download_variant')) {
                $table->dropColumn('download_variant');
            }

            if (Schema::hasColumn('download_unlocks', 'package_code')) {
                $table->dropColumn('package_code');
            }
        });
    }
};

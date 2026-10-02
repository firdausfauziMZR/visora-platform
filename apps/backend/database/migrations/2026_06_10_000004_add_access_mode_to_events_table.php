<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            if (! Schema::hasColumn('events', 'access_mode')) {
                $table->string('access_mode', 32)
                    ->default('code_grants_access')
                    ->after('access_code')
                    ->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            if (Schema::hasColumn('events', 'access_mode')) {
                $table->dropColumn('access_mode');
            }
        });
    }
};

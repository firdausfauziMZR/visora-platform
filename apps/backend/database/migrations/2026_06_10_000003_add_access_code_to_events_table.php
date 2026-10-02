<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            if (! Schema::hasColumn('events', 'access_code')) {
                $table->string('access_code', 32)->nullable()->unique()->after('slug');
            }
        });

        DB::table('events')
            ->whereNull('access_code')
            ->get(['id'])
            ->each(function (object $event): void {
                do {
                    $code = Str::upper(Str::random(8));
                } while (DB::table('events')->where('access_code', $code)->exists());

                DB::table('events')
                    ->where('id', $event->id)
                    ->update(['access_code' => $code]);
            });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            if (Schema::hasColumn('events', 'access_code')) {
                $table->dropColumn('access_code');
            }
        });
    }
};

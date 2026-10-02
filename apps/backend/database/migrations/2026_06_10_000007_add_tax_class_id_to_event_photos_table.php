<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_photos', function (Blueprint $table): void {
            if (! Schema::hasColumn('event_photos', 'tax_class_id')) {
                $table->unsignedBigInteger('tax_class_id')->nullable()->after('status');
            }
        });

        $taxClassId = DB::table('lunar_tax_classes')
            ->where('default', true)
            ->value('id')
            ?: DB::table('lunar_tax_classes')->value('id');

        if ($taxClassId) {
            DB::table('event_photos')
                ->whereNull('tax_class_id')
                ->update(['tax_class_id' => $taxClassId]);
        }
    }

    public function down(): void
    {
        Schema::table('event_photos', function (Blueprint $table): void {
            if (Schema::hasColumn('event_photos', 'tax_class_id')) {
                $table->dropColumn('tax_class_id');
            }
        });
    }
};

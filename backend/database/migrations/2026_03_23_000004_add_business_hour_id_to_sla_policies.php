<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sla_policies', function (Blueprint $table) {
            $table->foreignId('business_hour_id')->nullable()->after('is_active')
                ->constrained('business_hours')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sla_policies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_hour_id');
        });
    }
};

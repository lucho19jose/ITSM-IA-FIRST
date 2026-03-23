<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Replace the existing string 'plan' column with an enum
            $table->string('plan')->default('free')->change();

            $table->json('plan_limits')->nullable()->after('plan');
            $table->timestamp('plan_expires_at')->nullable()->after('plan_limits');
            $table->integer('max_agents')->default(3)->after('plan_expires_at');
            $table->integer('max_tickets_per_month')->default(100)->after('max_agents');
            $table->integer('max_storage_mb')->default(500)->after('max_tickets_per_month');
            $table->json('features')->nullable()->after('max_storage_mb');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'plan_limits',
                'plan_expires_at',
                'max_agents',
                'max_tickets_per_month',
                'max_storage_mb',
                'features',
            ]);

            $table->string('plan')->default('trial')->change();
        });
    }
};

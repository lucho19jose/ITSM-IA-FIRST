<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_catalog_items', function (Blueprint $table) {
            $table->boolean('requires_approval')->default(false)->after('approval_required');
            $table->foreignId('approval_workflow_id')
                ->nullable()
                ->after('requires_approval')
                ->constrained('approval_workflows')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('service_catalog_items', function (Blueprint $table) {
            $table->dropForeign(['approval_workflow_id']);
            $table->dropColumn(['requires_approval', 'approval_workflow_id']);
        });
    }
};

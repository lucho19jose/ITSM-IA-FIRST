<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('category_id')->constrained()->nullOnDelete();
            $table->string('subcategory', 100)->nullable()->after('department_id');
            $table->string('item', 100)->nullable()->after('subcategory');
            $table->string('impact', 20)->nullable()->after('priority');
            $table->string('urgency', 20)->nullable()->after('impact');
            $table->string('status_details', 100)->nullable()->after('status');
            $table->string('approval_status', 30)->nullable()->after('urgency');
            $table->timestamp('planned_start_date')->nullable()->after('due_date');
            $table->timestamp('planned_end_date')->nullable()->after('planned_start_date');
            $table->string('planned_effort', 50)->nullable()->after('planned_end_date');
            $table->string('association_type', 30)->nullable()->after('planned_effort');
            $table->string('major_incident_type', 50)->nullable()->after('association_type');
            $table->string('contact_number', 30)->nullable()->after('major_incident_type');
            $table->string('requester_location', 255)->nullable()->after('contact_number');
            $table->string('specific_subject', 255)->nullable()->after('requester_location');
            $table->unsignedInteger('customers_impacted')->nullable()->after('specific_subject');
            $table->json('impacted_locations')->nullable()->after('customers_impacted');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn([
                'department_id', 'subcategory', 'item', 'impact', 'urgency',
                'status_details', 'approval_status', 'planned_start_date',
                'planned_end_date', 'planned_effort', 'association_type',
                'major_incident_type', 'contact_number', 'requester_location',
                'specific_subject', 'customers_impacted', 'impacted_locations',
            ]);
        });
    }
};

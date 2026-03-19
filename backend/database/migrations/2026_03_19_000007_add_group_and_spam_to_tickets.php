<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('agent_group_id')->nullable()->after('department_id')->constrained('agent_groups')->nullOnDelete();
            $table->boolean('is_spam')->default(false)->after('resolution_notes');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['agent_group_id']);
            $table->dropColumn(['agent_group_id', 'is_spam']);
        });
    }
};

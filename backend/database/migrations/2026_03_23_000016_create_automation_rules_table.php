<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('execution_order')->default(0);
            $table->boolean('stop_on_match')->default(false);

            $table->enum('trigger_event', [
                'ticket_created',
                'ticket_updated',
                'ticket_assigned',
                'ticket_closed',
                'ticket_reopened',
                'sla_approaching',
                'sla_breached',
                'comment_added',
                'time_based',
            ]);

            $table->json('conditions');
            $table->json('actions');

            $table->timestamp('last_triggered_at')->nullable();
            $table->integer('trigger_count')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active', 'trigger_event']);
            $table->index(['tenant_id', 'execution_order']);
        });

        Schema::create('automation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rule_id')->constrained('automation_rules')->cascadeOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->string('trigger_event');
            $table->boolean('conditions_matched');
            $table->json('actions_executed')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('executed_at');

            $table->index(['tenant_id', 'rule_id']);
            $table->index(['tenant_id', 'executed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_logs');
        Schema::dropIfExists('automation_rules');
    }
};

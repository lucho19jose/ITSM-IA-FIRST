<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->enum('type', ['standard', 'normal', 'emergency'])->default('normal');
            $table->enum('status', [
                'draft', 'submitted', 'assessment', 'cab_review', 'approved', 'rejected',
                'scheduled', 'implementing', 'implemented', 'review', 'closed',
            ])->default('draft');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('risk_level', ['low', 'medium', 'high', 'very_high'])->default('medium');
            $table->enum('impact', ['low', 'medium', 'high', 'extensive'])->default('medium');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->text('reason_for_change');
            $table->text('implementation_plan')->nullable();
            $table->text('rollback_plan')->nullable();
            $table->text('test_plan')->nullable();
            $table->text('risk_assessment')->nullable();
            $table->timestamp('scheduled_start')->nullable();
            $table->timestamp('scheduled_end')->nullable();
            $table->timestamp('actual_start')->nullable();
            $table->timestamp('actual_end')->nullable();
            $table->text('review_notes')->nullable();
            $table->text('cab_decision')->nullable();
            $table->foreignId('cab_decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cab_decided_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'requested_by']);
            $table->index(['tenant_id', 'assigned_to']);
            $table->index(['tenant_id', 'scheduled_start', 'scheduled_end']);
        });

        Schema::create('change_request_ticket', function (Blueprint $table) {
            $table->id();
            $table->foreignId('change_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->enum('relationship_type', ['caused_by', 'related', 'implements'])->default('related');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['change_request_id', 'ticket_id']);
        });

        Schema::create('change_request_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('change_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approver_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->default('cab_member');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('comment')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['change_request_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_request_approvals');
        Schema::dropIfExists('change_request_ticket');
        Schema::dropIfExists('change_requests');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('ticket_number')->unique();
            $table->string('title');
            $table->text('description');
            $table->string('type');
            $table->string('status')->default('open');
            $table->string('priority')->default('medium');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requester_id')->constrained('users');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sla_policy_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('due_date')->nullable();
            $table->timestamp('response_due_at')->nullable();
            $table->timestamp('resolution_due_at')->nullable();
            $table->json('tags')->nullable();
            $table->json('custom_fields')->nullable();
            $table->tinyInteger('satisfaction_rating')->nullable();
            $table->text('satisfaction_comment')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('status');
            $table->index('priority');
            $table->index('assigned_to');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};

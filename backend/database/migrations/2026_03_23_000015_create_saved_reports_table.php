<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('report_type', ['tickets', 'agents', 'sla', 'categories', 'trends', 'custom']);
            $table->json('config');
            $table->boolean('is_shared')->default(false);
            $table->string('schedule_cron')->nullable();
            $table->json('schedule_emails')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'is_shared']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_reports');
    }
};

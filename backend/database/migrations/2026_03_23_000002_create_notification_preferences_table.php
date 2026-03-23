<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->enum('channel', ['email', 'in_app', 'both'])->default('both');
            $table->boolean('ticket_created')->default(true);
            $table->boolean('ticket_assigned')->default(true);
            $table->boolean('ticket_commented')->default(true);
            $table->boolean('ticket_closed')->default(true);
            $table->boolean('sla_warning')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};

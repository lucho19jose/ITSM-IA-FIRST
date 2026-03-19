<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_associations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->string('type', 20); // parent, child, related, cause
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['ticket_id', 'related_ticket_id']);
            $table->index('related_ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_associations');
    }
};

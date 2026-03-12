<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->text('input');
            $table->text('output');
            $table->string('model');
            $table->decimal('confidence', 5, 2)->nullable();
            $table->boolean('was_accepted')->nullable();
            $table->integer('tokens_used')->nullable();
            $table->integer('processing_time_ms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_suggestions');
    }
};

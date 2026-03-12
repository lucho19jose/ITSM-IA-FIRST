<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('field_key', 50);
            $table->string('label', 100);
            $table->string('field_type', 30);
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_system')->default(true);
            $table->integer('sort_order')->default(0);
            $table->json('options')->nullable();
            $table->string('default_value', 255)->nullable();
            $table->string('placeholder', 255)->nullable();
            $table->string('section', 20)->default('main');
            $table->string('help_text', 255)->nullable();
            $table->json('role_visibility')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'field_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_form_fields');
    }
};

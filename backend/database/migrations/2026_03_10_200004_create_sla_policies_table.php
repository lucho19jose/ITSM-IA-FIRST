<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('priority');
            $table->integer('response_time');
            $table->integer('resolution_time');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_policies');
    }
};

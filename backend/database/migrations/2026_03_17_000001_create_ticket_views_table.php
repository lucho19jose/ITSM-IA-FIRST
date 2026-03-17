<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('icon')->default('bookmark');
            $table->json('filters'); // stores the filter configuration
            $table->json('columns')->nullable(); // optional column config
            $table->boolean('is_default')->default(false);
            $table->boolean('is_shared')->default(false); // admin can share views
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_views');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_base_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('knowledge_base_categories')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->longText('content');
            $table->text('excerpt')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('author_id')->constrained('users');
            $table->integer('views_count')->default(0);
            $table->integer('helpful_count')->default(0);
            $table->integer('not_helpful_count')->default(0);
            $table->boolean('is_public')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->fullText(['title', 'content']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_base_articles');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->json('fields')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_type_id')->constrained('asset_types')->cascadeOnDelete();
            $table->string('name');
            $table->string('asset_tag');
            $table->string('serial_number')->nullable();
            $table->enum('status', ['active', 'inactive', 'maintenance', 'retired', 'lost', 'disposed'])->default('active');
            $table->enum('condition', ['new', 'good', 'fair', 'poor', 'broken'])->default('good');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('location')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 10, 2)->nullable();
            $table->date('warranty_expiry')->nullable();
            $table->string('vendor')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('mac_address')->nullable();
            $table->json('custom_fields')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'asset_tag']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'asset_type_id']);
            $table->index('assigned_to');
        });

        Schema::create('asset_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('target_asset_id')->constrained('assets')->cascadeOnDelete();
            $table->enum('relationship_type', ['contains', 'depends_on', 'connected_to', 'installed_on', 'runs_on']);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['source_asset_id', 'target_asset_id']);
        });

        Schema::create('asset_ticket', function (Blueprint $table) {
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->unique(['asset_id', 'ticket_id']);
        });

        Schema::create('asset_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->text('description');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['asset_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_logs');
        Schema::dropIfExists('asset_ticket');
        Schema::dropIfExists('asset_relationships');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('asset_types');
    }
};

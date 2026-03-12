<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_vip')->default(false)->after('is_active');
            $table->foreignId('department_id')->nullable()->after('is_vip')->constrained()->nullOnDelete();
            $table->string('phone', 30)->nullable()->after('department_id');
            $table->string('location', 255)->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn(['is_vip', 'department_id', 'phone', 'location']);
        });
    }
};

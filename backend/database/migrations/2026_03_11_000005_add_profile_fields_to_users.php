<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('job_title', 100)->nullable()->after('location');
            $table->string('timezone', 50)->default('America/Lima')->after('job_title');
            $table->string('language', 10)->default('es')->after('timezone');
            $table->string('avatar_path', 255)->nullable()->after('language');
            $table->text('signature')->nullable()->after('avatar_path');
            $table->boolean('is_available_for_assignment')->default(true)->after('signature');
            $table->string('time_format', 5)->default('12h')->after('is_available_for_assignment');
            $table->string('address', 255)->nullable()->after('time_format');
            $table->string('work_phone', 30)->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'job_title', 'timezone', 'language', 'avatar_path',
                'signature', 'is_available_for_assignment', 'time_format',
                'address', 'work_phone',
            ]);
        });
    }
};

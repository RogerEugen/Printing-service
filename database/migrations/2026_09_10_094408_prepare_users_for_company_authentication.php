<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->after('id');
            $table->string('role')->default('employee')->after('password');
            $table->boolean('is_active')->default(true)->after('role');
        });

        DB::table('users')->orderBy('id')->each(function (object $user): void {
            $base = Str::lower(Str::slug($user->name ?: Str::before($user->email, '@'), '')) ?: 'user';
            DB::table('users')->where('id', $user->id)->update([
                'username' => Str::limit($base, 40, '').$user->id,
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable(false)->change()->unique();
            $table->dropColumn(['name', 'email', 'email_verified_at']);
        });

        Schema::dropIfExists('password_reset_tokens');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable();
            $table->string('email')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'role', 'is_active']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }
};

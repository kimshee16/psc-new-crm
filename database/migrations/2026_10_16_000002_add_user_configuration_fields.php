<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 120)->nullable();
            $table->string('last_name', 120)->nullable();
            $table->string('phone_country_code', 8)->nullable();
            $table->string('phone_number', 40)->nullable();
            $table->string('picture_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('portal_invite_status', 40)->default('Invite Sent');
        });

        DB::table('users')->orderBy('id')->each(function ($user) {
            $parts = preg_split('/\s+/', trim($user->name), 2);
            DB::table('users')->where('id', $user->id)->update([
                'first_name' => $parts[0] ?? '', 'last_name' => $parts[1] ?? '',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn([
            'first_name', 'last_name', 'phone_country_code', 'phone_number', 'picture_path',
            'is_active', 'portal_invite_status',
        ]));
    }
};

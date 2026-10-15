<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'default_office_code')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('default_office_code', 40)->nullable()->after('offices');
            });
        }

        if (! Schema::hasColumn('users', 'include_in_round_robin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('include_in_round_robin')->default(true)->after('default_office_code');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'include_in_round_robin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('include_in_round_robin');
            });
        }

        if (Schema::hasColumn('users', 'default_office_code')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('default_office_code');
            });
        }
    }
};

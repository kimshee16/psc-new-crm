<?php

use App\Models\User;
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
        if (! Schema::hasColumn('users', 'account_type')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('account_type')->default(User::ACCOUNT_TYPE_ADMIN)->after('password');
            });
        }

        if (! Schema::hasColumn('users', 'offices')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('offices')->nullable()->after('account_type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'offices')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('offices');
            });
        }

        if (Schema::hasColumn('users', 'account_type')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('account_type');
            });
        }
    }
};

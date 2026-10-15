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
        Schema::table('leads', function (Blueprint $table) {
            if (! Schema::hasColumn('leads', 'prospective_service')) {
                $table->string('prospective_service', 80)->default('Study')->after('priority');
            }

            if (! Schema::hasColumn('leads', 'critical_date')) {
                $table->date('critical_date')->nullable()->after('prospective_service');
            }

            if (! Schema::hasColumn('leads', 'secondary_counsellor')) {
                $table->string('secondary_counsellor', 120)->nullable()->after('highest_education_level');
            }

            if (! Schema::hasColumn('leads', 'migration_agent')) {
                $table->string('migration_agent', 120)->nullable()->after('secondary_counsellor');
            }

            if (! Schema::hasColumn('leads', 'notes')) {
                $table->text('notes')->nullable()->after('enquiry');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('leads', 'prospective_service') ? 'prospective_service' : null,
                Schema::hasColumn('leads', 'critical_date') ? 'critical_date' : null,
                Schema::hasColumn('leads', 'secondary_counsellor') ? 'secondary_counsellor' : null,
                Schema::hasColumn('leads', 'migration_agent') ? 'migration_agent' : null,
                Schema::hasColumn('leads', 'notes') ? 'notes' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};

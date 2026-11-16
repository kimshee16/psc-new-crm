<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_activities', function (Blueprint $table) {
            $table->unsignedBigInteger('application_id')->nullable()->change();
            $table->foreignId('lead_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['kind', 'assigned_to_user_id', 'due_date'], 'tasks_assignee_due_index');
        });

        // Older application tasks had no assignee field in their entry form.
        DB::table('application_activities')->where('kind', 'tasks')->whereNull('assigned_to_user_id')
            ->update(['assigned_to_user_id' => DB::raw('(SELECT primary_counsellor_id FROM applications WHERE applications.id = application_activities.application_id)')]);
    }

    public function down(): void
    {
        // Retain nullable application IDs so rollback never deletes standalone/lead tasks.
        Schema::table('application_activities', function (Blueprint $table) {
            $table->dropIndex('tasks_assignee_due_index');
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropConstrainedForeignId('lead_id');
        });
    }
};

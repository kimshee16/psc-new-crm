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
            $table->string('request_type', 80)->nullable();
            $table->string('request_type_other')->nullable();
            $table->date('received_date')->nullable();
            $table->date('lodged_date')->nullable();
            $table->string('request_letter_path')->nullable();
            $table->string('request_letter_name')->nullable();
            $table->string('proof_of_lodgement_path')->nullable();
            $table->string('proof_of_lodgement_name')->nullable();
            $table->text('notes')->nullable();
            $table->index(['kind', 'status', 'received_date'], 'rfi_status_received_index');
        });

        // Keep earlier RFI rows and their text. Unknown dates/files remain unrecorded.
        DB::table('application_activities')->where('kind', 'rfi')->update([
            'request_type' => 'Other', 'request_type_other' => DB::raw('title'),
        ]);
        DB::table('application_activities')->where('kind', 'rfi')
            ->where(fn ($query) => $query->whereNull('status')->orWhereIn('status', ['', 'Pending']))
            ->update(['status' => 'Received']);
    }

    public function down(): void
    {
        Schema::table('application_activities', function (Blueprint $table) {
            $table->dropIndex('rfi_status_received_index');
            $table->dropColumn(['request_type', 'request_type_other', 'received_date', 'lodged_date',
                'request_letter_path', 'request_letter_name', 'proof_of_lodgement_path', 'proof_of_lodgement_name', 'notes']);
        });
    }
};

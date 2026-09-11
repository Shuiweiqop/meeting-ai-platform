<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            // When the meeting actually happened. Nullable: older rows and
            // uploads that skip the field fall back to created_at at read time.
            $table->timestamp('meeting_date')->nullable()->after('duration_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn('meeting_date');
        });
    }
};

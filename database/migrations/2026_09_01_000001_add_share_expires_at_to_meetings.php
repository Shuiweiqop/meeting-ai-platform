<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            // When the public share link stops working. A leaked link would
            // otherwise grant unauthenticated access forever until manual revoke.
            $table->timestamp('share_expires_at')->nullable()->after('share_token');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn('share_expires_at');
        });
    }
};

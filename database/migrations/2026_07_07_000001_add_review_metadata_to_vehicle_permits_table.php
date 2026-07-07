<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReviewMetadataToVehiclePermitsTable extends Migration
{
    public function up()
    {
        Schema::table('vehicle_permits', function (Blueprint $table) {
            $table->foreignId('reviewed_by')
                ->nullable()
                ->after('route_raw')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_note')->nullable()->after('reviewed_at');
        });
    }

    public function down()
    {
        Schema::table('vehicle_permits', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['reviewed_by', 'reviewed_at', 'review_note']);
        });
    }
}

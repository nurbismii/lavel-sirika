<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePermitRouteSegmentsTable extends Migration
{
    public function up()
    {
        Schema::create('permit_route_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_permit_id')->constrained('vehicle_permits')->onDelete('cascade');
            $table->foreignId('road_segment_id')->constrained('road_segments')->restrictOnDelete();
            $table->unsignedInteger('sequence')->default(1);
            $table->timestamps();
            $table->unique(['vehicle_permit_id', 'sequence'], 'permit_route_segment_permit_sequence_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('permit_route_segments');
    }
}

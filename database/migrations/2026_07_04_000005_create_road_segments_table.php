<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRoadSegmentsTable extends Migration
{
    public function up()
    {
        Schema::create('road_segments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('name');
            $table->string('start_location')->nullable();
            $table->string('end_location')->nullable();
            $table->json('polyline_json')->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('road_segments');
    }
}

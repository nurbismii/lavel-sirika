<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateParkingLocationsTable extends Migration
{
    public function up()
    {
        Schema::create('parking_locations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('parking_locations');
    }
}

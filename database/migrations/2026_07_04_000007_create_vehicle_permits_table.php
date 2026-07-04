<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVehiclePermitsTable extends Migration
{
    public function up()
    {
        Schema::create('vehicle_permits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $table->foreignId('parking_location_id')->nullable()->constrained('parking_locations')->nullOnDelete();
            $table->string('permit_color', 32)->nullable();
            $table->text('reason')->nullable();
            $table->string('approval_status', 32)->default('approved')->index();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->string('source', 32)->default('manual')->index();
            $table->foreignId('source_import_id')->nullable()->constrained('import_batches')->nullOnDelete();
            $table->text('route_raw')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('vehicle_permits');
    }
}

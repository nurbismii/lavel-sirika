<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateVehiclePermitParkingLocationsTable extends Migration
{
    public function up()
    {
        Schema::create('vehicle_permit_parking_locations', function (Blueprint $table) {
            $table->foreignId('vehicle_permit_id')
                ->constrained('vehicle_permits')
                ->cascadeOnDelete();
            $table->foreignId('parking_location_id')
                ->constrained('parking_locations')
                ->restrictOnDelete();
            $table->timestamps();

            $table->unique([
                'vehicle_permit_id',
                'parking_location_id',
            ], 'vehicle_permit_parking_locations_unique');
        });

        $timestamp = now();

        DB::table('vehicle_permits')
            ->select('id', 'parking_location_id')
            ->whereNotNull('parking_location_id')
            ->orderBy('id')
            ->chunkById(500, function ($permits) use ($timestamp) {
                $rows = $permits->map(function ($permit) use ($timestamp) {
                    return [
                        'vehicle_permit_id' => $permit->id,
                        'parking_location_id' => $permit->parking_location_id,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                })->all();

                DB::table('vehicle_permit_parking_locations')->insertOrIgnore($rows);
            });
    }

    public function down()
    {
        Schema::dropIfExists('vehicle_permit_parking_locations');
    }
}

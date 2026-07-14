<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddUniqueEmployeeVehicleToVehiclePermitsTable extends Migration
{
    public function up()
    {
        $this->ensureNoDuplicateVehiclePlates();
        $this->ensureNoDuplicatePermits();

        Schema::table('vehicles', function (Blueprint $table) {
            $table->unique('plate_number', 'vehicles_plate_number_unique');
        });

        Schema::table('vehicle_permits', function (Blueprint $table) {
            $table->unique(['employee_id', 'vehicle_id'], 'vehicle_permits_employee_vehicle_unique');
        });
    }

    public function down()
    {
        Schema::table('vehicle_permits', function (Blueprint $table) {
            $table->dropUnique('vehicle_permits_employee_vehicle_unique');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropUnique('vehicles_plate_number_unique');
        });
    }

    private function ensureNoDuplicateVehiclePlates(): void
    {
        $duplicate = DB::table('vehicles')
            ->select('plate_number', DB::raw('COUNT(*) as duplicate_count'))
            ->groupBy('plate_number')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if (! $duplicate) {
            return;
        }

        $vehicleIds = DB::table('vehicles')
            ->where('plate_number', $duplicate->plate_number)
            ->orderBy('id')
            ->pluck('id')
            ->implode(',');

        throw new \RuntimeException(sprintf(
            'Cannot add vehicles_plate_number_unique because duplicate vehicle plate rows exist for plate_number=%s vehicle_ids=%s. Clean duplicate vehicles before running this migration.',
            $duplicate->plate_number,
            $vehicleIds
        ));
    }

    private function ensureNoDuplicatePermits(): void
    {
        $duplicate = DB::table('vehicle_permits')
            ->select('employee_id', 'vehicle_id', DB::raw('COUNT(*) as duplicate_count'))
            ->groupBy('employee_id', 'vehicle_id')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if (! $duplicate) {
            return;
        }

        throw new \RuntimeException(sprintf(
            'Cannot add vehicle_permits_employee_vehicle_unique because duplicate permit rows exist for employee_id=%s vehicle_id=%s. Clean duplicate permits before running this migration.',
            $duplicate->employee_id,
            $duplicate->vehicle_id
        ));
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddUniqueEmployeePlateToVehiclesTable extends Migration
{
    public function up()
    {
        $this->ensureNoDuplicateVehicleIdentities();

        Schema::table('vehicles', function (Blueprint $table) {
            $table->unique(['employee_id', 'plate_number'], 'vehicles_employee_plate_unique');
        });
    }

    public function down()
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropUnique('vehicles_employee_plate_unique');
        });
    }

    private function ensureNoDuplicateVehicleIdentities(): void
    {
        $duplicate = DB::table('vehicles')
            ->select('employee_id', 'plate_number', DB::raw('COUNT(*) as duplicate_count'))
            ->groupBy('employee_id', 'plate_number')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if (! $duplicate) {
            return;
        }

        throw new \RuntimeException(sprintf(
            'Cannot add vehicles_employee_plate_unique because duplicate vehicle rows exist for employee_id=%s plate_number=%s. Clean duplicate vehicles before running this migration.',
            $duplicate->employee_id,
            $duplicate->plate_number
        ));
    }
}

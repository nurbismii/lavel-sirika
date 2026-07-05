<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class VehicleUniqueMigrationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function vehicle_unique_migration_reports_duplicate_existing_vehicle_rows()
    {
        $employee = Employee::create([
            'nik' => '200115677',
            'name' => 'FITRIAWATI',
            'status' => 'active',
        ]);

        $migration = new \AddUniqueEmployeePlateToVehiclesTable();
        $migration->down();

        Vehicle::create([
            'employee_id' => $employee->id,
            'plate_number' => 'DT 4423 CI',
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
        ]);

        Vehicle::create([
            'employee_id' => $employee->id,
            'plate_number' => 'DT 4423 CI',
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot add vehicles_employee_plate_unique because duplicate vehicle rows exist');

        $migration->up();
    }
}

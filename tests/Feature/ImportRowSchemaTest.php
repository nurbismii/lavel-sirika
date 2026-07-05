<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImportRowSchemaTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function import_rows_table_has_required_columns()
    {
        $this->assertTrue(Schema::hasColumns('import_rows', [
            'id',
            'import_batch_id',
            'row_number',
            'status',
            'raw_data',
            'normalized_data',
            'errors',
            'warnings',
            'created_employee_id',
            'created_vehicle_id',
            'created_permit_id',
            'created_at',
            'updated_at',
        ]));
    }

    /** @test */
    public function import_batch_has_rows_relationship_and_status_constants()
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN_HR,
            'status' => User::STATUS_ACTIVE,
        ]);

        $batch = ImportBatch::create([
            'filename' => 'sample.xlsx',
            'uploaded_by' => $user->id,
            'status' => ImportBatch::STATUS_PREVIEWED,
        ]);

        $row = ImportRow::create([
            'import_batch_id' => $batch->id,
            'row_number' => 5,
            'status' => ImportRow::STATUS_VALID,
            'raw_data' => ['nik' => '200115677'],
            'normalized_data' => ['nik' => '200115677'],
            'errors' => [],
            'warnings' => [],
        ]);

        $this->assertSame(ImportBatch::STATUS_PREVIEWED, $batch->status);
        $this->assertTrue($batch->rows->first()->is($row));
        $this->assertSame(['nik' => '200115677'], $row->normalized_data);
    }
}

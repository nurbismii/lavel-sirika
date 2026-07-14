<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\RoadSegment;
use App\Models\User;
use App\Models\Employee;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use App\Services\Imports\PermitExcelImportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Tests\TestCase;

class ImportExcelPreviewTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_creates_preview_batch_from_excel_file()
    {
        $this->seedRoadSegments(['Y1', 'D2', 'Z1', 'D3']);

        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_HR,
            'status' => User::STATUS_ACTIVE,
        ]);

        $file = $this->excelFile([
            ['', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['VDNI Formulir', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['序号 No', '摩托车牌号 Plat Motor', '姓名 Nama', '工号 Nik', '部门 Dep', '科室 Bagian', '岗位 Jabatan', '停放地点 Lokasi Parkir', '行驶路线 Rute Kendaraan', '进厂原因 Alasan Masuk', '通行证颜色 Warna kartu izin masuk', '联系方式 Nomor kontak', '审批结果 Hasil Persetujuan', 'KET', 'DIVISI'],
            ['1', 'DT 4423 CI', 'FITRIAWATI', '200115677', 'GENERAL AFFAIR', 'GA KANTOR', 'ADMIN', 'GA-MES1-P01', 'Y1→D2→Z1→D3→GA-MES1-P01', 'OFFICE', 'BIRU 蓝色', '0812', '√', '', 'GENERAL AFFAIR'],
            ['2', '', 'HARLINA', '211129282', 'GENERAL AFFAIR', 'GA KANTOR', 'ADMIN', 'GA-MES1-P01', 'Y1→D2', 'OFFICE', 'KUNING 黄色', '0813', '√', '', 'GENERAL AFFAIR'],
            ['3', 'DT 9999 AA', 'JUMRAN', '16101080', 'GENERAL AFFAIR', 'GA KEBERSIHAN', 'ADMIN', 'GA-MES3-P01', '', 'OFFICE', 'MERAH 红色', '0814', '√', '', 'GENERAL AFFAIR'],
        ]);

        $batch = app(PermitExcelImportService::class)->preview($file, $admin);

        $this->assertSame(ImportBatch::STATUS_PREVIEWED, $batch->fresh()->status);
        $this->assertSame(3, $batch->fresh()->total_rows);
        $this->assertSame(1, $batch->fresh()->success_rows);
        $this->assertSame(1, $batch->fresh()->failed_rows);
        $this->assertSame(1, $batch->fresh()->review_rows);
        $this->assertSame(3, $batch->rows()->count());
        $this->assertNull($batch->fresh()->error_summary);
        $this->assertDatabaseHas('import_rows', ['row_number' => 4, 'status' => ImportRow::STATUS_VALID]);
        $this->assertDatabaseHas('import_rows', ['row_number' => 5, 'status' => ImportRow::STATUS_INVALID]);
        $this->assertDatabaseHas('import_rows', ['row_number' => 6, 'status' => ImportRow::STATUS_NEEDS_REVIEW]);
    }

    /** @test */
    public function it_marks_batch_failed_when_header_is_missing()
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_HR,
            'status' => User::STATUS_ACTIVE,
        ]);

        $file = $this->excelFile([
            ['Wrong', 'Header'],
            ['1', '2'],
        ]);

        $batch = app(PermitExcelImportService::class)->preview($file, $admin);

        $this->assertSame(ImportBatch::STATUS_FAILED, $batch->fresh()->status);
        $this->assertSame(0, $batch->rows()->count());
        $this->assertStringContainsString('Header Excel tidak valid', $batch->fresh()->error_summary);
    }

    /** @test */
    public function it_rejects_preview_for_user_without_preview_access()
    {
        $user = User::factory()->create([
            'role' => User::ROLE_SECURITY,
            'status' => User::STATUS_ACTIVE,
        ]);

        $file = $this->excelFile([
            ['Wrong', 'Header'],
            ['1', '2'],
        ]);

        try {
            app(PermitExcelImportService::class)->preview($file, $user);
            $this->fail('Expected preview() to reject unauthorized uploader.');
        } catch (AuthorizationException $exception) {
            $this->assertSame(0, ImportBatch::count());
            $this->assertStringContainsString('tidak diizinkan', $exception->getMessage());
        }
    }

    /** @test */
    public function it_marks_batch_failed_when_file_storage_fails()
    {
        Log::spy();

        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_HR,
            'status' => User::STATUS_ACTIVE,
        ]);

        $file = $this->excelFile([
            ['Wrong', 'Header'],
            ['1', '2'],
        ]);

        $service = new class(
            app(\App\Services\Imports\PermitImportHeaderMapper::class),
            app(\App\Services\Imports\PermitImportRowNormalizer::class),
            app(\App\Services\Imports\PermitImportFileValidator::class)
        ) extends PermitExcelImportService {
            protected function storeFile(UploadedFile $file): string
            {
                throw new RuntimeException('Simulated storage failure.');
            }
        };

        $batch = $service->preview($file, $admin);

        $this->assertSame(ImportBatch::STATUS_FAILED, $batch->fresh()->status);
        $this->assertSame(1, ImportBatch::count());
        $this->assertSame(0, $batch->rows()->count());
        $this->assertSame('File Excel gagal diproses. Periksa format file lalu coba kembali.', $batch->error_summary);
        Log::shouldHaveReceived('warning')->once()->withArgs(function ($message, $context) use ($batch) {
            return $message === 'Permit Excel import failed.'
                && $context['import_batch_id'] === $batch->id
                && $context['exception'] === RuntimeException::class
                && ! array_key_exists('path', $context)
                && ! array_key_exists('contents', $context);
        });
    }

    /** @test */
    public function service_rejects_a_disguised_non_excel_file_before_creating_a_batch()
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_HR,
            'status' => User::STATUS_ACTIVE,
        ]);

        $path = tempnam(sys_get_temp_dir(), 'sirika-invalid-');
        file_put_contents($path, 'not an excel workbook');
        $file = new UploadedFile($path, 'disguised.xlsx', 'text/plain', null, true);

        try {
            app(PermitExcelImportService::class)->preview($file, $admin);
            $this->fail('Expected invalid file validation exception.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('Tipe file', $exception->getMessage());
        }

        $this->assertSame(0, ImportBatch::count());
    }

    /** @test */
    public function preview_rejects_workbooks_over_the_configured_row_limit_without_partial_rows()
    {
        config(['sirika.import.max_rows' => 2]);
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_HR,
            'status' => User::STATUS_ACTIVE,
        ]);

        $file = $this->excelFile([
            ['NIK', 'Nama', 'Plat Motor', 'Lokasi Parkir', 'Rute Kendaraan'],
            ['1', 'A', 'DT 1 AA', 'P1', 'Y1'],
            ['2', 'B', 'DT 2 AA', 'P1', 'Y1'],
            ['3', 'C', 'DT 3 AA', 'P1', 'Y1'],
        ]);

        $batch = app(PermitExcelImportService::class)->preview($file, $admin);

        $this->assertSame(ImportBatch::STATUS_FAILED, $batch->status);
        $this->assertStringContainsString('maksimal 2 baris', $batch->error_summary);
        $this->assertSame(0, $batch->rows()->count());
    }

    /** @test */
    public function it_rejects_duplicate_permit_identities_during_preview()
    {
        $this->seedRoadSegments(['Y1']);

        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_HR,
            'status' => User::STATUS_ACTIVE,
        ]);

        $employee = Employee::create([
            'nik' => '200115678',
            'name' => 'PERMIT LAMA',
            'status' => 'active',
        ]);
        $vehicle = Vehicle::create([
            'employee_id' => $employee->id,
            'plate_number' => 'DT 4715 BO',
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
        ]);
        VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'permit_color' => 'biru',
            'status' => VehiclePermit::STATUS_ACTIVE,
            'source' => 'manual',
        ]);

        $file = $this->excelFile([
            ['', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['VDNI Formulir', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['Plat Motor', 'Nama', 'NIK', 'Dep', 'Bagian', 'Jabatan', 'Lokasi Parkir', 'Rute Kendaraan', 'Alasan Masuk', 'Warna Kartu Izin Masuk', 'Nomor Kontak', 'Hasil Persetujuan', 'DIVISI'],
            ['DT 4423 CI', 'FITRIAWATI', '200115677', 'GENERAL AFFAIR', 'GA KANTOR', 'ADMIN', 'GA-MES1-P01', 'Y1', 'OFFICE', 'BIRU', '0812', 'disetujui', 'GENERAL AFFAIR'],
            ['DT 4423 CI', 'FITRIAWATI', '200115677', 'GENERAL AFFAIR', 'GA KANTOR', 'ADMIN', 'GA-MES1-P01', 'Y1', 'OFFICE', 'BIRU', '0812', 'disetujui', 'GENERAL AFFAIR'],
            ['DT 4714 BO', 'FITRIAWATI', '200115677', 'GENERAL AFFAIR', 'GA KANTOR', 'ADMIN', 'GA-MES1-P01', 'Y1', 'OFFICE', 'BIRU', '0812', 'disetujui', 'GENERAL AFFAIR'],
            ['DT 4715 BO', 'PERMIT LAMA', '200115678', 'GENERAL AFFAIR', 'GA KANTOR', 'ADMIN', 'GA-MES1-P01', 'Y1', 'OFFICE', 'BIRU', '0812', 'disetujui', 'GENERAL AFFAIR'],
        ]);

        $batch = app(PermitExcelImportService::class)->preview($file, $admin);

        $this->assertSame(2, $batch->fresh()->success_rows);
        $this->assertSame(2, $batch->fresh()->failed_rows);
        $this->assertDatabaseHas('import_rows', [
            'import_batch_id' => $batch->id,
            'row_number' => 5,
            'status' => ImportRow::STATUS_INVALID,
        ]);
        $this->assertDatabaseHas('import_rows', [
            'import_batch_id' => $batch->id,
            'row_number' => 6,
            'status' => ImportRow::STATUS_VALID,
        ]);
        $this->assertDatabaseHas('import_rows', [
            'import_batch_id' => $batch->id,
            'row_number' => 7,
            'status' => ImportRow::STATUS_INVALID,
        ]);
        $this->assertContains(
            'NIK dan plat kendaraan duplikat pada baris 4.',
            $batch->rows()->where('row_number', 5)->first()->errors
        );
        $this->assertContains(
            'Izin kendaraan untuk NIK dan plat ini sudah terdaftar.',
            $batch->rows()->where('row_number', 7)->first()->errors
        );
    }

    private function seedRoadSegments(array $codes)
    {
        foreach ($codes as $code) {
            RoadSegment::create([
                'code' => $code,
                'name' => 'Jalan ' . $code,
                'status' => 'active',
            ]);
        }
    }

    private function excelFile(array $rows)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                $sheet->setCellValueByColumnAndRow($columnIndex + 1, $rowIndex + 1, $value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'sirika-import-') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile(
            $path,
            'sample.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}

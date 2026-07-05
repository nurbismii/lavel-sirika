<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportExcelPreviewHttpTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_can_view_import_page_with_upload_form()
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_HR,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($admin)->get(route('imports.index'))
            ->assertOk()
            ->assertSee('Upload Excel')
            ->assertSee('Daftar Batch Import')
            ->assertSee('name="file"', false);
    }

    /** @test */
    public function security_cannot_upload_import_file()
    {
        Storage::fake('local');

        $security = User::factory()->create([
            'role' => User::ROLE_SECURITY,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($security)->post(route('imports.store'), [
            'file' => UploadedFile::fake()->create('sample.xlsx', 10),
        ])->assertForbidden();
    }

    /** @test */
    public function non_excel_upload_is_rejected()
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_HR,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($admin)->from(route('imports.index'))->post(route('imports.store'), [
            'file' => UploadedFile::fake()->create('sample.txt', 10, 'text/plain'),
        ])->assertRedirect(route('imports.index'))
            ->assertSessionHasErrors('file');
    }

    /** @test */
    public function admin_can_view_import_batch_preview()
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_HR,
            'status' => User::STATUS_ACTIVE,
        ]);

        $batch = ImportBatch::create([
            'filename' => 'sample.xlsx',
            'uploaded_by' => $admin->id,
            'total_rows' => 0,
            'status' => ImportBatch::STATUS_PREVIEWED,
        ]);

        $this->actingAs($admin)->get(route('imports.show', $batch))
            ->assertOk()
            ->assertSee('Preview Import')
            ->assertSee('sample.xlsx');
    }
}

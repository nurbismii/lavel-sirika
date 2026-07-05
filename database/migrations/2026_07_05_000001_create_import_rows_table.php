<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateImportRowsTable extends Migration
{
    public function up()
    {
        Schema::create('import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained('import_batches')->onDelete('cascade');
            $table->unsignedInteger('row_number');
            $table->string('status', 32)->index();
            $table->json('raw_data')->nullable();
            $table->json('normalized_data')->nullable();
            $table->json('errors')->nullable();
            $table->json('warnings')->nullable();
            $table->foreignId('created_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('created_vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('created_permit_id')->nullable()->constrained('vehicle_permits')->nullOnDelete();
            $table->timestamps();

            $table->unique(['import_batch_id', 'row_number']);
            $table->index(['import_batch_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('import_rows');
    }
}

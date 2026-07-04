<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmployeesTable extends Migration
{
    public function up()
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('nik', 64)->unique();
            $table->string('name');
            $table->string('department')->nullable();
            $table->string('section')->nullable();
            $table->string('position')->nullable();
            $table->string('division')->nullable();
            $table->string('contact_number', 64)->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('employees');
    }
}

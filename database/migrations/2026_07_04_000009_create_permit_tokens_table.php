<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePermitTokensTable extends Migration
{
    public function up()
    {
        Schema::create('permit_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_permit_id')->constrained('vehicle_permits')->onDelete('cascade');
            $table->string('token_hash', 128)->unique();
            $table->string('status', 32)->default('active')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('permit_tokens');
    }
}

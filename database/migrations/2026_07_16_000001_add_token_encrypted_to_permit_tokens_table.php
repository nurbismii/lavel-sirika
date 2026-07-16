<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTokenEncryptedToPermitTokensTable extends Migration
{
    public function up()
    {
        Schema::table('permit_tokens', function (Blueprint $table) {
            $table->text('token_encrypted')->nullable();
        });
    }

    public function down()
    {
        Schema::table('permit_tokens', function (Blueprint $table) {
            $table->dropColumn('token_encrypted');
        });
    }
}

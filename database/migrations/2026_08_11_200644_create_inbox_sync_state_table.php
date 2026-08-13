<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('inbox_sync_state', function (Blueprint $table) {
            $table->id();
            $table->string('account')->unique();          // IMAP account identifier, e.g. 'default'
            $table->unsignedBigInteger('last_uid')->default(0); // last successfully processed IMAP UID
            $table->timestamp('last_synced_at')->nullable();    // timestamp of last completed sync
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('inbox_sync_state');
    }
};

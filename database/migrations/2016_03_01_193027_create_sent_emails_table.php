<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use ABCreche\MailTracker\Model\SentEmail;

class CreateSentEmailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection((new SentEmail)->getConnectionName())->create('sent_emails', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('uuid')->unique();
            $table->text('headers')->nullable();
            $table->string('sender')->nullable();
            $table->string('recipient')->nullable();
            $table->string('subject')->nullable();
            $table->text('content')->nullable();
            $table->integer('opens')->nullable();
            $table->timestamp('opens_at')->nullable();
            $table->integer('clicks')->nullable();
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
        Schema::connection((new SentEmail())->getConnectionName())->drop('sent_emails');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDiscordWebhookToSettings extends Migration
{
    public function up()
    {
        // No changes needed to schema as we use a key-value 'market_settings' table
    }

    public function down()
    {
        // No changes needed
    }
}

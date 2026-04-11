<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGroupsToMarketItemData extends Migration
{
    public function up()
    {
        Schema::table('market_item_data', function (Blueprint $table) {
            $table->string('group_name')->nullable()->index();
            $table->string('category_name')->nullable()->index();
        });
    }

    public function down()
    {
        Schema::table('market_item_data', function (Blueprint $table) {
            $table->dropColumn(['group_name', 'category_name']);
        });
    }
}

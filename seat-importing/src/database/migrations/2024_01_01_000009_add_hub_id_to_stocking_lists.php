<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHubIdToStockingLists extends Migration
{
    public function up()
    {
        Schema::table('market_stocking_lists', function (Blueprint $table) {
            $table->unsignedBigInteger('hub_id')->nullable()->after('user_id');
            $table->foreign('hub_id')->references('id')->on('market_hubs')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('market_stocking_lists', function (Blueprint $table) {
            $table->dropForeign(['hub_id']);
            $table->dropColumn('hub_id');
        });
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSortOrderToSystemTracks extends Migration
{
    public function up()
    {
        Schema::table('seat_dashboard_system_tracks', function (Blueprint $table) {
            $table->integer('sort_order')->default(0);
        });
    }

    public function down()
    {
        Schema::table('seat_dashboard_system_tracks', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
}

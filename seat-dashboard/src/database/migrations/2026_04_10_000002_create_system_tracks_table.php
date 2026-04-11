<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSystemTracksTable extends Migration
{
    public function up()
    {
        Schema::create('seat_dashboard_system_tracks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id');
            $table->integer('solar_system_id');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('solar_system_id')->references('system_id')->on('solar_systems')->onDelete('cascade');
            $table->unique(['user_id', 'solar_system_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('seat_dashboard_system_tracks');
    }
}

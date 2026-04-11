<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStockingListsTables extends Migration
{
    public function up()
    {
        Schema::create('market_stocking_lists', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id');
            $table->string('label');
            $table->boolean('is_collapsed')->default(false);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('market_stocking_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('list_id');
            $table->unsignedInteger('type_id');
            $table->float('quantity')->default(0);
            $table->timestamps();

            $table->foreign('list_id')->references('id')->on('market_stocking_lists')->onDelete('cascade');
            $table->unique(['list_id', 'type_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('market_stocking_items');
        Schema::dropIfExists('market_stocking_lists');
    }
}

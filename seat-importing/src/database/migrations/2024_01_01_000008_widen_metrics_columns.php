<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class WidenMetricsColumns extends Migration
{
    public function up()
    {
        Schema::table('market_item_data', function (Blueprint $table) {
            $table->double('markup_pct')->change();
            $table->double('weekly_profit')->change();
            $table->double('import_cost')->change();
        });
    }

    public function down()
    {
        // No down needed for widening types
    }
}

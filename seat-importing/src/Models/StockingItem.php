<?php

namespace Apokavkos\SeatImporting\Models;

use Illuminate\Database\Eloquent\Model;
use Seat\Eveapi\Models\Sde\InvType;

class StockingItem extends Model
{
    protected $table = 'market_stocking_items';
    protected $fillable = ['list_id', 'type_id', 'quantity'];

    public function list() { return $this->belongsTo(StockingList::class, 'list_id'); }
    public function type() { return $this->belongsTo(InvType::class, 'type_id', 'typeID'); }
}

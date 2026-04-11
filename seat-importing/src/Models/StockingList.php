<?php

namespace Apokavkos\SeatImporting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Seat\Web\Models\User;

class StockingList extends Model
{
    protected $table = 'market_stocking_lists';
    protected $fillable = ['user_id', 'hub_id', 'label', 'is_collapsed'];

    public function items(): HasMany { return $this->hasMany(StockingItem::class, 'list_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function hub(): BelongsTo { return $this->belongsTo(MarketHub::class, 'hub_id'); }
}

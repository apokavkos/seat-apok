<?php

namespace Apokavkos\SeatImporting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketHub extends Model
{
    protected $table = 'market_hubs';

    protected $fillable = [
        'name', 'solar_system_id', 'structure_id', 'region_id', 'isk_per_m3', 'is_active', 'notes',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'isk_per_m3' => 'float',
    ];

    public function itemData(): HasMany { return $this->hasMany(MarketItemData::class, 'hub_id'); }

    public function effectiveIskPerM3(): float
    {
        // Use per-hub value, default to 0 if not set
        return (float) ($this->isk_per_m3 ?? 0.0);
    }
}

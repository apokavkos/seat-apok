<?php

namespace Apokavkos\SeatDashboard\Models;

use Illuminate\Database\Eloquent\Model;
use Seat\Eveapi\Models\Sde\SolarSystem;
use Seat\Web\Models\User;

class SystemTrack extends Model
{
    protected $table = 'seat_dashboard_system_tracks';

    protected $fillable = [
        'user_id', 'solar_system_id', 'sort_order'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function solar_system()
    {
        return $this->belongsTo(SolarSystem::class, 'solar_system_id', 'system_id');
    }
}

<?php

namespace Apokavkos\SeatDashboard;

use Seat\Services\AbstractSeatPlugin;
use Seat\Web\Models\Acl\Role;
use Seat\Web\Models\Acl\Permission;

class SeatDashboardServiceProvider extends AbstractSeatPlugin
{
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/Http/routes.php');
        $this->loadViewsFrom(__DIR__ . '/resources/views', 'seat-dashboard');
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        // Automatically ensure the Permission exists and is linked to the Admin role
        try {
            $permission = Permission::firstOrCreate(['title' => 'seat-dashboard.view']);
            $role = Role::where('title', 'Administrator')->first();
            if ($role && $permission) {
                $role->permissions()->syncWithoutDetaching([$permission->id]);
            }
        } catch (\Exception $e) {
            // Silence if DB not ready
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/Config/package.sidebar.php', 'package.sidebar');
        $this->registerPermissions(__DIR__ . '/Config/Permissions/dashboard.permissions.php', 'seat-dashboard');
    }

    public function getName(): string { return "Custom Standalone Dashboard"; }
    public function getPackageRepositoryUrl(): string { return 'https://github.com/apokavkos/seat-dashboard'; }
    public function getPackagistPackageName(): string { return 'apokavkos/seat-dashboard'; }
    public function getPackagistVendorName(): string { return 'apokavkos'; }
}

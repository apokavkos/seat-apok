<?php

use Illuminate\Support\Facades\Route;

Route::group([
    'namespace' => 'Apokavkos\SeatDashboard\Http\Controllers',
    'prefix' => 'seat-dashboard',
    'middleware' => ['web', 'auth', 'locale', 'can:seat-dashboard.view'],
], function () {
    Route::get('/', 'DashboardController@index')->name('seat-dashboard::index');
    Route::get('/search-systems', 'DashboardController@searchSystems')->name('seat-dashboard::search-systems');
    Route::post('/add-system', 'DashboardController@addSystem')->name('seat-dashboard::add-system');
    Route::delete('/remove-system/{id}', 'DashboardController@removeSystem')->name('seat-dashboard::remove-system');
    Route::post('/reorder-systems', 'DashboardController@reorderSystems')->name('seat-dashboard::reorder-systems');
});

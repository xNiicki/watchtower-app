<?php

declare(strict_types=1);

use App\Livewire\Alerts\AlertList;
use App\Livewire\Apps\AppList;
use App\Livewire\Dashboard;
use App\Livewire\Infra\TargetDetail;
use App\Livewire\Infra\TargetList;
use App\Livewire\Logs\LogSearch;
use App\Livewire\Settings;
use Illuminate\Support\Facades\Route;

Route::get('/', Dashboard::class)->name('dashboard');
Route::get('/infra', TargetList::class)->name('infra.index');
Route::get('/infra/{targetId}', TargetDetail::class)->name('infra.show');
Route::get('/apps', AppList::class)->name('apps.index');
Route::get('/logs', LogSearch::class)->name('logs.index');
Route::get('/alerts', AlertList::class)->name('alerts.index');
Route::get('/settings', Settings::class)->name('settings');

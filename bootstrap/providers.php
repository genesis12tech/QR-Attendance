<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\FacultyPanelProvider;
use App\Providers\Filament\SuperAdminPanelProvider;

return [
    AppServiceProvider::class,
    SuperAdminPanelProvider::class,
    AdminPanelProvider::class,
    FacultyPanelProvider::class,
];

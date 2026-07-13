<?php

use Darvis\ApiLinkedin\Http\Controllers\LinkedInController;
use Illuminate\Support\Facades\Route;

/*
| The built-in OAuth routes. Prefix, middleware and route names come from
| config/linkedin.php. Can be disabled through `linkedin.routes.enabled`.
*/

Route::get('connect', [LinkedInController::class, 'connect'])
    ->name(config('linkedin.routes.connect_name', 'linkedin.connect'));

Route::get('callback', [LinkedInController::class, 'callback'])
    ->name(config('linkedin.routes.callback_name', 'linkedin.callback'));

<?php

use Darvis\ApiLinkedin\Http\Controllers\LinkedInController;
use Illuminate\Support\Facades\Route;

/*
| De ingebouwde OAuth-routes. Prefix, middleware en routenamen komen uit
| config/linkedin.php. Uitschakelbaar via `linkedin.routes.enabled`.
*/

Route::get('connect', [LinkedInController::class, 'connect'])
    ->name(config('linkedin.routes.connect_name', 'linkedin.connect'));

Route::get('callback', [LinkedInController::class, 'callback'])
    ->name(config('linkedin.routes.callback_name', 'linkedin.callback'));

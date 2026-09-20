<?php

use Darvis\ApiLinkedin\Http\Controllers\LinkedInController;
use Darvis\ApiLinkedin\Support\LinkedInConfig;
use Illuminate\Support\Facades\Route;

/*
| The built-in OAuth routes. Prefix, middleware and route names come from
| config/linkedin.php. Can be disabled through `linkedin.routes.enabled`.
*/

Route::get('connect', [LinkedInController::class, 'connect'])
    ->name(LinkedInConfig::connectRouteName());

Route::get('callback', [LinkedInController::class, 'callback'])
    ->name(LinkedInConfig::callbackRouteName());

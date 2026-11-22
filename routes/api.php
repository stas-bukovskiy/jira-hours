<?php

declare(strict_types=1);

use App\Http\Controllers\WeekReportController;
use Illuminate\Support\Facades\Route;

Route::get('/week', WeekReportController::class);

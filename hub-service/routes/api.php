<?php

use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\SchemaController;
use App\Http\Controllers\StepsController;
use App\Http\Controllers\EmployeeController as HubEmployeeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Hello World'
    ]);
});
Route::get('/checklists', [ChecklistController::class, 'index']);
Route::get('/steps', [StepsController::class, 'index']);
Route::get('/employees', [HubEmployeeController::class, 'index']);
Route::get('/employees/{id}', [HubEmployeeController::class, 'show'])->whereNumber('id');
Route::get('/schema/{step_id}', [SchemaController::class, 'show']);

<?php

use App\Models\SessionExport;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/session-exports/{export}/download', function (SessionExport $export) {
    abort_unless(
        $export->file_path && Storage::exists($export->file_path),
        404
    );

    return Storage::download($export->file_path);
})->name('session-exports.download')->middleware(['auth', 'signed']);

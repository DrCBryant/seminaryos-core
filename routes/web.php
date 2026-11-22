<?php

use App\Filament\Resources\OfficialTranscripts\Support\OfficialTranscriptView;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/admin/official-transcripts/{transcript}/print', [OfficialTranscriptView::class, 'print'])
    ->whereUuid('transcript')
    ->name('official-transcripts.print');

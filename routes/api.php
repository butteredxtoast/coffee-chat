<?php

use App\Http\Controllers\MemberController;
use App\Http\Controllers\MatchesController;
use Illuminate\Support\Facades\Route;

// Member routes
Route::resource('members', MemberController::class);
Route::post('members/match', [MemberController::class, 'match']);

// Match routes
Route::resource('matches', MatchesController::class);
Route::patch('matches/{match}/met', [MatchesController::class, 'markAsMet']);
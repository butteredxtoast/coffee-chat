<?php

use App\Http\Controllers\MemberController;
use App\Http\Controllers\MatchesController;
use App\Http\Controllers\SlackController;
use Illuminate\Support\Facades\Route;

// Member routes
Route::resource('members', MemberController::class);
Route::post('members/match', [MemberController::class, 'match']);

// Match routes
Route::resource('matches', MatchesController::class);
Route::patch('matches/{match}/met', [MatchesController::class, 'markAsMet']);

// Slack routes
Route::get('/slack/members', [SlackController::class, 'getMembers']);
Route::post('/slack/sync', [SlackController::class, 'syncChannelMembers']);
Route::post('/slack/command', [SlackController::class, 'handleCommand']);

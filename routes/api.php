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
// can possibly delete the below route
Route::patch('matches/{match}/met', [MatchesController::class, 'markAsMet']);
Route::post('/slack/interactions', [SlackController::class, 'handleInteraction']);
Route::delete('matches/{match}', [MatchesController::class, 'destroy']);
Route::delete('/matches', [MatchesController::class, 'destroyAll']);

// Slack routes
Route::get('/slack/members', [SlackController::class, 'getMembers']);
Route::post('/slack/sync', [SlackController::class, 'syncChannelMembers']);
Route::post('/slack/command', [SlackController::class, 'handleCommand']);
Route::post('/slack/interaction', [SlackController::class, 'handleInteraction']);

Route::get('/slack/message/{memberId}', [SlackController::class, 'sendTestMessage']);

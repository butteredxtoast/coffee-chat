<?php

namespace App\Http\Controllers;

use App\Services\SlackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SlackController extends Controller
{
    public function getMembers(SlackService $slack): JsonResponse
    {
        try {
            $members = $slack->getChannelMembers(config('services.slack.channel_id'));
            return response()->json($members);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}

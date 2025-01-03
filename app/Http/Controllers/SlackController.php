<?php

namespace App\Http\Controllers;

use App\Services\SlackService;
use App\Services\SlackSyncService;
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

    public function syncChannelMembers(SlackSyncService $syncer): JsonResponse
    {
        try {
            $success = $syncer->syncMembers(config('services.slack.channel_id'));
            return response()->json([
                'success' => $success,
                'message' => 'Members synced successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

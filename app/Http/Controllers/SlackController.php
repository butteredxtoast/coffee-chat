<?php

namespace App\Http\Controllers;

use App\Models\Member;
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

    public function handleCommand(Request $request): JsonResponse
    {
//        if (!$this->verifySlackSignature($request)) {
//            return response()->json(['error' => 'Invalid signature'], 401);
//        }

        $text = $request->get('text');
        $userId = $request->get('user_id');

        if ($text === 'met') {
            $member = Member::where('slack_id', $userId)->first();
            $match = $member?->currentMatch;

            if ($match) {
                $match->update([
                    'met' => true,
                    'met_confirmed_at' => now()
                ]);
                return response()->json(['text' => 'Great! I\'ve marked your coffee chat as met.']);
            }

            return response()->json(['text' => 'You don\'t have any pending coffee chats.']);
        } elseif ($text === 'revert') {
            $member = Member::where('slack_id', $userId)->first();
            $match = $member?->currentMatch;

            if ($match) {
                $match->update([
                    'met' => false,
                    'met_confirmed_at' => null
                ]);
                return response()->json(['text' => 'I\'ve reverted your coffee chat back to pending.']);
            }

            return response()->json(['text' => 'You don\'t have any pending coffee chats.']);
        }

        return response()->json(['text' => 'I\'m sorry, I didn\'t understand that command. Try "met" instead.']);
    }

    private function verifySlackSignature(Request $request): bool
    {
        $signature = $request->header('X-Slack-Signature');
        $timestamp = $request->header('X-Slack-Request-Timestamp');
        $signingSecret = config('services.slack.signing_secret');

        $baseString = "v0:{$timestamp}:{$request->getContent()}";
        $computedSignature = 'v0=' . hash_hmac('sha256', $baseString, $signingSecret);

        return hash_equals($computedSignature, $signature);
    }
}

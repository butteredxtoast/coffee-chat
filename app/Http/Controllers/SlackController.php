<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Matches; // Added Matches model
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

    public function handleInteraction(Request $request): JsonResponse
    {
        // ToDo: Verify Slack request signature for security

        // Parse the payload
        $payload = json_decode($request->input('payload'), true);

        // Check if this is a button click for meeting confirmation
        if ($payload['type'] === 'block_actions') {
            foreach ($payload['actions'] as $action) {
                if ($action['action_id'] === 'confirm_meeting') {
                    $matchId = $action['value'];

                    // Mark the match as met
                    $match = Matches::find($matchId);
                    if ($match) {
                        $match->update([
                            'met' => true,
                            'met_confirmed_at' => now()
                        ]);

                        // Respond to the user
                        return response()->json([
                            'response_type' => 'ephemeral',
                            'text' => "✅ Great! I've marked your coffee chat as complete. Thanks for letting me know!"
                        ]);
                    }
                }
            }
        }

        return response()->json([
            'response_type' => 'ephemeral',
            'text' => "Sorry, I couldn't process that action."
        ]);
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

    /**
     * Send a test message to a user via Slack
     *
     * @param int $memberId The ID of the member in our database
     * @param SlackService $slack The Slack service
     * @return JsonResponse
     */

    public function sendTestMessage(int $memberId, SlackService $slack): JsonResponse
    {
        try {
            $member = Member::findOrFail($memberId);

            if (empty($member->slack_id)) {
                return response()->json([
                    'success' => false,
                    'error' => 'This member does not have a Slack ID'
                ], 400);
            }

            $channelId = $slack->createGroupDM([$member->slack_id]);

            if (empty($channelId)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Could not create a DM channel with this member'
                ], 500);
            }

            $blocks = [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "Hello, world! This is a test message from the Coffee Chat Bot. ☕"
                    ]
                ],
                [
                    'type' => 'actions',
                    'elements' => [
                        [
                            'type' => 'button',
                            'text' => [
                                'type' => 'plain_text',
                                'text' => 'Test Button',
                                'emoji' => true
                            ],
                            'style' => 'primary',
                            'value' => (string) $member->id,
                            'action_id' => 'test_button_click'
                        ]
                    ]
                ]
            ];

            $messageSuccess = $slack->sendInteractiveMessage(
                $channelId,
                "Hello, world! This is a test message from the Coffee Chat Bot.", // Fallback text
                $blocks
            );

            if (!$messageSuccess) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to send interactive message'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Test message sent successfully',
                'channel_id' => $channelId,
                'member' => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'slack_id' => $member->slack_id
                ]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Member not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

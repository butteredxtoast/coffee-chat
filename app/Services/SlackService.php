<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Matches;
use Google\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SlackService
{
    protected PendingRequest $client;
    protected mixed $token;

    public function __construct()
    {
        $this->token = config('services.slack.bot_token');
        $this->client = Http::baseUrl('https://slack.com/api/')
            ->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->throw()
            ->retry(2);
    }

    /**
     * @throws ConnectionException
     */
    public function getChannelMembers(string $channelId): array
    {
        $response = $this->client->get('conversations.members', [
            'channel' => $channelId
        ]);

        return $response->json('members', []);
    }

    /**
     * @throws ConnectionException
     */
    public function createGroupDM(array $users): ?string
    {
        try {
            $response = $this->client->post('conversations.open', [
                'users' => implode(',', $users)
            ]);

            if (!$response->json('ok')) {
                Log::error('Failed to create DM:', [
                    'error' => $response->json('error'),
                    'users' => $users
                ]);
                return null;
            }

            return $response->json('channel.id');
        } catch (\Exception $e) {
            Log::error('Error creating DM:', [
                'error' => $e->getMessage(),
                'users' => $users
            ]);
            return null;
        }
    }

    /**
     * @throws ConnectionException
     */
    public function sendMessage(string $channelId, string $message): void
    {
        $this->client->post('chat.postMessage', [
            'channel' => $channelId,
            'text' => $message
        ]);
    }

    /**
     * Get all channels (including DMs and group DMs) that the bot is part of)
     * @return array
     * @throws ConnectionException
     */
    public function getAllBotChannels(): array
    {
        $allChannels = [];
        $cursor = null;

        do {
            $params = ['types' => 'public_channel,private_channel,mpim,im'];
            if ($cursor) {
                $params['cursor'] = $cursor;
            }

            $response = $this->client->get('conversations.list', $params);
            $channels = $response->json('channels', []);
            $cursor = $response->json('response_metadata.next_cursor', null);

            foreach ($channels as $channel) {
                try {
                    $memberResponse = $this->client->get('conversations.members', [
                        'channel' => $channel['id']
                    ]);
                    $channel['members'] = $memberResponse->json('members', []);
                    $allChannels[] = $channel;
                } catch (\Exception $e) {
                    Log::warning("Could not get members for channel {$channel['id']}: {$e->getMessage()}");
                }
            }
        } while ($cursor);

        return $allChannels;
    }

    /**
     * @throws ConnectionException
     */
    public function getUserInfo(string $userId): array
    {
        $response = $this->client->get('users.info', [
            'user' => $userId
        ]);

        return $response->json('user', []);
    }

    /**
     * @param string $channelId The Slack channel ID for the match
     * @return void
     */
    public function sendChannelSummary(string $channelId): void
    {
        $blocks = [
            [
                "type" => "section",
                "text" => [
                    "type" => "mrkdwn",
                    "text" => "🎉 New coffee chat matches have been created! Check your DMs for introductions."
                ]
            ],
            [
                "type" => "actions",
                "elements" => [
                    [
                        "type" => "button",
                        "text" => ["type" => "plain_text", "text" => "View All Matches"],
                        "url" => "https://docs.google.com/spreadsheets/d/" . config('services.google.sheets_id'),
                        "action_id" => "view_matches"
                    ]
                ]
            ]
        ];

        try {
            $this->client->post('chat.postMessage', [
                'channel' => $channelId,
                'blocks' => $blocks
            ]);
        } catch (\Exception $e) {
            Log::error('Cannot send message', [
                'error' => $e->getMessage(),
                'channel' => $channelId
            ]);
        }
    }

    /**
     * Send a message to a match group with a Google Calendar link
     * @param string $channelId The Slack channel ID for the match
     * @param Matches|null $match The match object containing member relations (optional)
     * @return bool Whether the message was sent successfully
     * @throws \DateMalformedStringException
     */
    public function sendMatchMessage(string $channelId, Matches $match = null): bool
    {
        $emails = [];
        $names = [];

        $members = [];
        if ($match) {
            if ($match->member1) $members[] = $match->member1;
            if ($match->member2) $members[] = $match->member2;
            if ($match->member3) $members[] = $match->member3;
        }

        foreach ($members as $member) {
            if (!empty($member->email)) {
                $emails[] = $member->email;
            }
            $names[] = $member->name ?? ('User ' . $member->slack_id);
        }

        $participationNames = implode(', ', $names);

        $calendarUrl = $this->buildGoogleCalendarUrl(
            'NP Coffee Chat with ' . $participationNames,
            'Get to know a fellow co-lead! ☕️',
            $emails
        );

        $blocks = [
            [
                "type" => "section",
                "text" => [
                    "type" => "mrkdwn",
                    "text" => "A new #np-coffee-chat connection begins! ✨ Coordinate your meetup here and enjoy discovering what you have in common. \n\nThe coffee's optional, but the conversation's guaranteed!"
                ]
            ],
            [
                "type" => "actions",
                "elements" => [
                    [
                        "type" => "button",
                        "text" => [
                            "type" => "plain_text",
                            "text" => "📅 Schedule a chat",
                            "emoji" => true
                        ],
                        "url" => $calendarUrl,
                        "action_id" => "schedule_coffee_chat"
                    ]
                ]
            ]
        ];

        try {
            $response = $this->client->post('chat.postMessage', [
                'channel' => $channelId,
                'blocks' => $blocks
            ]);

            return $response->json('ok', false);
        } catch (\Exception $e) {
            Log::error('Cannot send message', [
                'error' => $e->getMessage(),
                'channel' => $channelId
            ]);
            return false;
        }
    }

    private function handleUserNotFound(Member ...$members): void
    {
        foreach ($members as $member) {
            try {
                $this->getUserInfo($member->slack_id);
            } catch (\Exception $e) {
                $error = null;
                if ($e instanceof RequestException) {
                    $error = $e->response?->json('error');
                }

                if ($error === 'user_not_found') {
                    $member->update(['is_active' => false]);
                }
            }
        }
    }

    /**
     * Send a reminder message with an interactive button to a match group
     * @throws ConnectionException
     */
    public function sendReminderMessage(Matches $match): void
    {
        if (!$match->slack_channel_id) {
            Log::error('Cannot send reminder: Match has no Slack channel ID', [
                'match_id' => $match->id,
                'member1' => $match->member1?->slack_handle ?? 'unknown',
                'member2' => $match->member2?->slack_handle ?? 'unknown',
                'member3' => $match->member3?->slack_handle ?? null
            ]);
            return;
        }

        $blocks = [
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "Hey y'all! 👋 We'll be creating new matches at the end of the month. Have you had a chance to meet yet? If so, please confirm below!"
                ]
            ],
            [
                'type' => 'actions',
                'elements' => [
                    [
                        'type' => 'button',
                        'text' => [
                            'type' => 'plain_text',
                            'text' => 'Yes, we met! ☕',
                            'emoji' => true
                        ],
                        'style' => 'primary',
                        'value' => (string) $match->id,
                        'action_id' => 'confirm_meeting'
                    ]
                ]
            ]
        ];

        $this->client->post('chat.postMessage', [
            'channel' => $match->slack_channel_id,
            'blocks' => json_encode($blocks)
        ]);
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
            // Find the member
            $member = Member::findOrFail($memberId);

            // Check if the member has a valid Slack ID
            if (empty($member->slack_id)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Member does not have a valid Slack ID'
                ], 400);
            }

            // Create a direct message channel with the user
            $channelId = $slack->createGroupDM([$member->slack_id]);

            // Check if channel creation was successful
            if (empty($channelId)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Could not create a direct message with this user'
                ], 500);
            }

            // Send the test message
            $slack->sendMessage($channelId, "Hello, world! This is a test message from the Coffee Chat Bot.");

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
        } catch (ModelNotFoundException $e) {
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

    /**
     * Send a message with interactive blocks to a channel
     *
     * @param string $channelId The channel ID to send the message to
     * @param string $text Fallback text for the message
     * @param array $blocks Array of block elements
     * @return bool Whether the message was sent successfully
     */
    public function sendInteractiveMessage(string $channelId, string $text, array $blocks): bool
    {
        try {
            $response = $this->client->post('chat.postMessage', [
                'channel' => $channelId,
                'text' => $text,
                'blocks' => $blocks
            ]);

            return $response->json('ok', false);
        } catch (\Exception $e) {
            Log::error('Cannot send interactive message', [
                'error' => $e->getMessage(),
                'channel' => $channelId
            ]);
            return false;
        }
    }

    /**
     * Build a Google Calendar URL with pre-filled event details
     * @param string $title Event title
     * @param string $description Event description
     * @param array $guests Array of guest email addresses
     * @param int $durationMinutes Event duration in minutes (default: 30)
     * @return string Formatted Google Calendar URL
     * @throws \DateMalformedStringException
     */
    private function buildGoogleCalendarUrl(string $title, string $description, array $guests, int $durationMinutes = 30): string
    {
        $baseUrl = 'https://calendar.google.com/calendar/render';

        $startTime = new \DateTime('+2 days');
        $startTime->setTime(10, 0, 0);

        $endTime = clone $startTime;
        $endTime->modify("+{$durationMinutes} minutes");

        $formattedStart = $startTime->format('Ymd\THis\Z');
        $formattedEnd = $endTime->format('Ymd\THis\Z');

        $params = [
            'action' => 'TEMPLATE',
            'text' => $title,
            'details' => $description,
            'dates' => $formattedStart . '/' . $formattedEnd,
        ];

        if (!empty($guests)) {
            $params['add'] = implode(',', $guests);
        }

        return $baseUrl . '?' . http_build_query($params);
    }
}

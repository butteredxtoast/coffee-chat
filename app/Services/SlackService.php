<?php

namespace App\Services;

use App\Models\Member;
use Google\Collection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
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
     * @throws ConnectionException
     */
    public function getUserInfo(string $userId): array
    {
        $response = $this->client->get('users.info', [
            'user' => $userId
        ]);

        return $response->json('user', []);
    }

    public function createMatchDM(Member $member1, Member $member2, Member $member3): ?string
    {
        try {
            $response = $this->client->post('conversations.open', [
                'users' => implode(',', [$member1->slack_id, $member2->slack_id])
            ]);

            return $response->json('channel.id');
        } catch (\Exception $e) {
            $error = null;
            if ($e instanceof RequestException) {
                $error = $e->response?->json('error');
            }

            match($error) {
                'cannot_dm_bot' => Log::error('Cannot create DM - one of the users is a bot', [
                    'members' => [$member1->id, $member2->id]
                ]),
                'user_not_found' => $this->handleUserNotFound($member1, $member2),
                default => Log::error('Slack API error:', [
                    'error' => $error,
                    'members' => [$member1->id, $member2->id]
                ])
            };

            return null;
        }
    }

    /**
     * @throws ConnectionException
     */
    public function sendChannelSummary(string $channelId, Collection $matches): void
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

        $this->client->post('chat.postMessage', [
            'channel' => $channelId,
            'blocks' => $blocks
        ]);
    }

    public function sendMatchMessage(string $channelId): bool
    {
        $blocks = [
            [
                "type" => "section",
                "text" => [
                    "type" => "mrkdwn",
                    "text" => "A new #np-coffee-chat connection begins! ✨ Coordinate your meetup here and enjoy discovering what you have in common. \n\nThe coffee's optional, but the conversation's guaranteed!"
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
}

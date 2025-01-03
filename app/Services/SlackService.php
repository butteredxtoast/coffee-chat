<?php

namespace App\Services;

use App\Models\Member;
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

    public function createMatchDM(Member $member1, Member $member2): ?string
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


    public function sendMatchMessage(string $channelId, Member $member1, Member $member2): bool
    {
        $message = "Say hello to your new connection!\n\n";

        if ($member1->notes) {
            $message .= "{$member1->name} has this to say: {$member1->notes}\n";
        }
        if ($member2->notes) {
            $message .= "{$member2->name} has this to say: {$member2->notes}\n";
        }

        try {
            $response = $this->client->post('chat.postMessage', [
                'channel' => $channelId,
                'text' => $message,
                'unfurl_links' => false
            ]);

            if (!$response->json('ok')) {
                Log::error('Failed to send match message:', [
                    'error' => $response->json('error'),
                    'channel' => $channelId
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Cannot send message - channel not accessible', [
                'channel' => $channelId,
                'members' => [$member1->id, $member2->id],
                'error' => $e->getMessage()
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

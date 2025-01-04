<?php

namespace App\Services;

use App\Models\Member;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SlackSyncService
{
    protected SlackService $slack;

    public function __construct(SlackService $slack)
    {
        $this->slack = $slack;
    }

    /**
     * @throws ConnectionException
     */
    public function syncMembers(string $channelId): bool
    {
        try {
            $slackMembers = $this->slack->getChannelMembers($channelId);

            foreach ($slackMembers as $slackId) {
                $userInfo = $this->slack->getUserInfo($slackId);

                if ($userInfo['is_bot'] || empty($userInfo['profile']['email'])) {
                    continue;
                }

                Member::updateOrCreate(
                    ['slack_id' => $slackId],
                    [
                        'name' => $userInfo['real_name'] ?? $userInfo['name'],
                        'email' => $userInfo['profile']['email'],
                        'slack_handle' => $userInfo['profile']['display_name'] ?? null,
                        'is_active' => true
                    ]
                );
            }

            Member::whereNotIn('slack_id', $slackMembers)
                ->update(['is_active' => false]);

            Log::info('Slack member sync completed successfully');
            return true;
        } catch (\Exception $e) {
            Log::error('Error syncing Slack members: ' . $e->getMessage());
            throw $e;
        }
    }
}

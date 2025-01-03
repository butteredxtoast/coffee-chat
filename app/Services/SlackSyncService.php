<?php

namespace App\Services;

use App\Models\Member;
use Illuminate\Http\Client\ConnectionException;
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
    public function syncMembers(string $channelId): void
    {
        try {
            $slackMembers = $this->slack->getChannelMembers($channelId);

            foreach ($slackMembers as $slackId) {
                $userInfo = $this->slack->getUserInfo($slackId);

                Member::updateOrCreate(
                    ['slack_id' => $slackId],
                    [
                        'name' => $userInfo['real_name'] ?? $userInfo['name'],
                        'email' => $userInfo['profile']['email'] ?? null,
                        'avatar' => $userInfo['profile']['image_192'] ?? null,
                        'is_active' => true
                    ]
                );
            }

            Member::whereNotIn('slack_id', $slackMembers)
                ->update(['is_active' => false]);

            Log::info('Slack member sync completed successfully');
        } catch (\Exception $e) {
            Log::error('Error syncing Slack members: ' . $e->getMessage());
            throw $e;
        }
    }
}

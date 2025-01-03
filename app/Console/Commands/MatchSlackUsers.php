<?php

namespace App\Console\Commands;

use App\Services\SlackService;
use App\Services\SlackSyncService;
use App\Services\MatchingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MatchSlackUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:match-slack-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Slack users and create new matches';

    /**
     * Execute the console command.
     */
    public function handle(
        SlackService $slack,
        SlackSyncService $syncer,
        MatchingService $matcher
    ): int
    {
        Log::info('Starting user matching process');

        try {
            // Sync members from Slack
            $syncer->syncMembers(config('services.slack.channel_id'));
            Log::info('Members synced from Slack');

            // Create new matches
            $matches = $matcher->createMatches();
            Log::info(sprintf('Created %d matches', $matches->count()));

            // Create DMs and send messages
            foreach ($matches as $match) {
                $dmChannel = $slack->createGroupDM([$match->member1, $match->member2]);

                if ($dmChannel) {
                    $slack->sendMatchMessage($dmChannel, $match->member1, $match->member2);
                    Log::info('Created DM and send message', ['match_id' => $match->id]);
                } else {
                    Log::error('Failed to create DM', ['match_id' => $match->id]);
                }
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            Log::error('Error during matching process: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

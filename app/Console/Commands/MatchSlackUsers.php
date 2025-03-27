<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Services\GoogleSheetService;
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
        MatchingService $matcher,
        GoogleSheetService $sheets
    ): int
    {
        Log::info('Starting user matching process');

        try {
            // Sync members from Slack
            $syncer->syncMembers(config('services.slack.channel_id'));
            Log::info('Members synced from Slack');

            // Create new matches
            $matches = $matcher->createMatches();
            Log::info('Created matches', ['matches' => $matches->count()]);

            $sheetsUpdated = $sheets->appendMatches($matches);

            if ($sheetsUpdated) {
                $slack->sendChannelSummary(
                    config('services.slack.channel_id'),
                    $matches,
                );
            }

            // Create DMs and send messages
            foreach ($matches as $match) {
                $memberIds = array_filter([
                    $match->member1?->slack_id,
                    $match->member2?->slack_id,
                    $match->member3?->slack_id ?? null,
                ]);

                $dmChannel = $slack->createGroupDM($memberIds);

                if ($dmChannel) {
                    $match->update(['slack_channel_id' => $dmChannel]);

                    $slack->sendMatchMessage($dmChannel, $match);
                    Log::info('Created DM and send message', ['match_id' => $match->id]);
                } else {
                    Log::error('Failed to create DM', ['match_id' => $match->id]);
                }
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            Log::error('Error during matching process', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }
}

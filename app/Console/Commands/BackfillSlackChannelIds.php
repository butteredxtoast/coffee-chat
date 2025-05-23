<?php

namespace App\Console\Commands;

use App\Models\Matches;
use App\Services\SlackService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackfillSlackChannelIds extends Command
{
    protected $signature = 'app:backfill-slack-channel-ids';
    protected $description = 'Find and store Slack channel IDs for existing matches';

    public function handle(SlackService $slackService): int
    {
        $this->info('Starting Slack channel ID backfill process');

        try {
            // Debug database schema
            $columns = \Schema::getColumnListing('matches');
            $this->info('Columns in matches table: ' . implode(', ', $columns));

            // Count total matches
            $totalMatches = Matches::count();
            $this->info('Total matches in database: ' . $totalMatches);

            // Check matches with channel IDs
            $matchesWithChannelId = Matches::whereNotNull('slack_channel_id')->count();
            $this->info('Matches WITH channel IDs: ' . $matchesWithChannelId);

            // Check matches without channel IDs
            $matchesWithoutChannelId = Matches::whereNull('slack_channel_id')->count();
            $this->info('Matches WITHOUT channel IDs (should be found): ' . $matchesWithoutChannelId);

            // Try a direct query to verify
            $rawResults = \DB::select('SELECT COUNT(*) as count FROM matches WHERE slack_channel_id IS NULL');
            $this->info('Raw SQL count of matches without channel IDs: ' . $rawResults[0]->count);

            // Get all channels the bot is in
            $channels = $this->getAllBotChannels($slackService);
            $this->info('Found ' . count($channels) . ' channels');

            $emptyStringChannels = Matches::where('slack_channel_id', '')->count();
            $this->info('Matches with empty string channel IDs: ' . $emptyStringChannels);

            // Get all channels the bot is in
            $channels = $this->getAllBotChannels($slackService);
            $this->info('Found ' . count($channels) . ' channels');

            $matches = Matches::whereNull('slack_channel_id')->get();
            $this->info('Found ' . $matches->count() . ' matches without channel IDs');

            $matchesUpdated = 0;

            foreach ($matches as $match) {
                $memberIds = array_filter([
                    $match->member1?->slack_id ?? null,
                    $match->member2?->slack_id ?? null,
                    $match->member3?->slack_id ?? null,
                ]);

                $matchingChannel = $this->findMatchingChannel($channels, $memberIds, $match->id);

                if ($matchingChannel) {
                    $match->update(['slack_channel_id' => $matchingChannel['id']]);
                    $matchesUpdated++;
                    $this->info("Updated match ID {$match->id} with channel ID {$matchingChannel['id']}");
                } else {
                    $this->warn("Could not find match ID {$match->id}");
                }
            }

            $this->info("Backfill complete. Updated {$matchesUpdated} out of {$matches->count()} matches.");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error during backfill: ' . $e->getMessage());
            Log::error('Error during channel ID backfill: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    private function getAllBotChannels(SlackService $slack): array
    {
        // We need to add this method to SlackService
        return $slack->getAllBotChannels();
    }

    private function findMatchingChannel(array $channels, array $memberIds, int $matchId): ?array
    {
        // Debug the members we're looking for
        $this->info("Match ID {$matchId} - Looking for members: " . implode(', ', $memberIds));

        // Sort member IDs for consistent comparison
        sort($memberIds);

        // Identify bot user ID from actual channels
        $botUserId = "U0877FS72CC"; // Hardcoded based on log analysis
        $this->info("Using bot ID: {$botUserId}");

        $bestMatch = null;
        $bestMatchSize = PHP_INT_MAX;

        foreach ($channels as $channel) {
            // Skip channels with no members
            if (!isset($channel['members']) || empty($channel['members'])) {
                continue;
            }

            // Filter out the bot ID from channel members
            $channelMembers = array_values(array_diff($channel['members'], [$botUserId]));
            $channelId = $channel['id'];

            // Debug channel info
            $this->info("Channel {$channelId} has " . count($channelMembers) . " members (excluding bot)");

            // Check if ALL match members are in this channel
            $allMembersFound = true;
            foreach ($memberIds as $memberId) {
                if (!in_array($memberId, $channelMembers)) {
                    $allMembersFound = false;
                    break;
                }
            }

            // If all members are in this channel AND it's smaller than our current best match
            if ($allMembersFound && count($channelMembers) < $bestMatchSize) {
                $this->info("Found potential match: Channel {$channelId} with " . count($channelMembers) . " members");
                $bestMatch = $channel;
                $bestMatchSize = count($channelMembers);

                // If we find a channel with exactly the right number of members, that's ideal
                if (count($channelMembers) === count($memberIds)) {
                    $this->info("PERFECT MATCH FOUND! Channel {$channelId} has exactly the right members");
                    break;  // Exit loop - we found an exact match
                }
            }
        }

        if ($bestMatch) {
            $this->info("BEST MATCH: Channel {$bestMatch['id']} with {$bestMatchSize} members for match ID {$matchId}");
            return $bestMatch;
        }

        return null;
    }
}

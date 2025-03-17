<?php

namespace App\Console\Commands;

use App\Models\Matches;
use App\Services\SlackService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendMatchReminders extends Command
{
    protected $signature = 'app:send-match-reminders';
    protected $description = 'Send reminders to matches that have not confirmed meeting after 30 days';

    public function handle(SlackService $slack): int
    {
        Log::info('Starting match reminder process');

        try {
            // $thirtyDaysAgo = Carbon::now()->subDays(30);
            $thirtyDaysAgo = Carbon::now();
            Log::info('Checking for matches older than', ['date' => $thirtyDaysAgo->toDateTimeString()]);
            
            $matches = Matches::where('matched_at', '<=', $thirtyDaysAgo)
                ->where('met', false)
                ->where('is_current', true)
                ->get();

            Log::info('Found matches requiring reminders', ['count' => $matches->count()]);

            foreach ($matches as $match) {
                try {
                    Log::info('Sending reminder for match', [
                        'match_id' => $match->id,
                        'member1' => $match->member1->slack_handle ?? 'unknown',
                        'member2' => $match->member2->slack_handle ?? 'unknown',
                        'member3' => $match->member3->slack_handle ?? null,
                        'matched_at' => $match->matched_at
                    ]);
                    
                    $slack->sendReminderMessage($match);
                    
                    Log::info('Successfully sent reminder for match', ['match_id' => $match->id]);
                } catch (\Exception $e) {
                    Log::error('Error sending reminder for match', [
                        'match_id' => $match->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            Log::error('Error in match reminder process: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

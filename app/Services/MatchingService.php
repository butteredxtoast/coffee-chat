<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Matches;
use Illuminate\Support\Collection;

class MatchingService
{
    public function createMatches(): Collection
    {
        $availableMembers = Member::whereDoesntHave('currentMatch')->get();
        $matches = new Collection();

        while ($availableMembers->count() >= 2) {
            $member1 = $availableMembers->shift();

            $member2 = $availableMembers->first(function ($member) use ($member1) {
                return !$member1->hasMetWith($member);
            });

            if ($member2) {
                $availableMembers = $availableMembers->filter(fn($m) => $m->id !== $member2->id);

                $match = Matches::create([
                    'member1_id' => $member1->id,
                    'member2_id' => $member2->id,
                    'matched_at' => now(),
                    'is_current' => true
                ]);

                $matches->push($match);
            }
        }

        return $matches;
    }
}

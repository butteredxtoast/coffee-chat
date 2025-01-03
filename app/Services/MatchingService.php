<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Matches;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class MatchingService
{
    /**
     * Creates matches between members who haven't met before
     * @return Collection<int, Matches>
     */
    public function createMatches(): Collection
    {
        /** @var EloquentCollection<int, Member> $availableMembers */
        $availableMembers = Member::query()->whereDoesntHave('currentMatch')->get();
        $matches = new Collection();

        while ($availableMembers->count() >= 2) {
            /** @var Member $member1 */
            $member1 = $availableMembers->shift();

            /** @var Member|null $member2 */
            $member2 = $availableMembers->first(function (Member $member) use ($member1): bool {
                return !$member1->hasMetWith($member);
            });

            if ($member2) {
                $availableMembers = $availableMembers->filter(fn(Member $m) => $m->id !== $member2->id);

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

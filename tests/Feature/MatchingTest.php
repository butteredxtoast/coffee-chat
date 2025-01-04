<?php

namespace Tests\Feature;

use App\Models\Member;
use Tests\TestCase;

class MatchingTest extends TestCase
{
    public function test_members_can_be_matched(): void
    {
        $this->markTestSkipped('Skipping test_members_can_be_matched');

        $members = Member::factory(6)->create();

        $response = $this->post('/api/matches/create');

        $response->assertStatus(200);
        $this->assertDatabaseCount('matches', 3);
    }

    public function test_members_cannot_be_matched_twice(): void
    {
        $this->markTestSkipped('Skipping test_members_cannot_be_matched_twice');

        $member1 = Member::factory()->create();
        $member2 = Member::factory()->create();

        $match = $this->post('/api/matches/create');
        $this->post("/api/matches/{$match->id}/met");

        $newMatch = $this->post('/api/matches/create');
        $this->assertNotEquals($member1->id, $newMatch->member1_id);
        $this->assertNotEquals($member2->id, $newMatch->member2_id);
    }
}

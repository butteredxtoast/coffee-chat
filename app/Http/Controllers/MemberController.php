<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Services\MatchingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;

class MemberController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $members = Member::all();
        return response()->json($members);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'pronouns' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'preferred_contact_method' => 'required|in:slack,phone,email',
            'slack_handle' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'required|email|unique:members',
            'anniversary_year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'notes' => 'nullable|string',
        ]);

        $member = Member::create($validated);
        return response()->json($member, Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     */
    public function show(Member $member): JsonResponse
    {
        return response()->json($member);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Member $member): JsonResponse  
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'pronouns' => 'sometimes|nullable|string|max:255',
            'city' => 'sometimes|nullable|string|max:255',
            'preferred_contact_method' => 'sometimes|in:slack,phone,email',
            'slack_handle' => 'sometimes|nullable|string|max:255',
            'phone' => 'sometimes|nullable|string|max:255',
            'email' => 'sometimes|email|unique:members,email,' . $member->id,
            'anniversary_year' => 'sometimes|nullable|integer|min:1900|max:' . (date('Y') + 1),
            'notes' => 'sometimes|nullable|string',
        ]);

        $member->update($validated);
        return response()->json($member);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Member $member): Response
    {
        $member->delete();
        return response()->noContent();
    }

    public function match(MatchingService $matchingService): JsonResponse
    {
        $matches = $matchingService->createMatches();

        return response()->json([
            'message' => 'Matching process completed',
            'matches' => $matches->map(function ($match) {
                return [
                    'id' => $match->id,
                    'member1' => $match->member1->name,
                    'member2' => $match->member2->name,
                    'matched_at' => $match->matched_at
                ];
            }) 
        ]);
    }
}

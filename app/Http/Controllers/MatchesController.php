<?php

namespace App\Http\Controllers;

use App\Models\Matches;
use App\Services\MatchingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MatchesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $matches = Matches::with(['member1', 'member2', 'member3'])->get();

        return response()->json($matches);
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
    public function store(Request $request)
    {
        $validated = $request->validate([
            'member1_id' => 'required|exists:members,id',
            'member2_id' => 'required|exists:members,id|different:member1_id',
            'member3_id' => 'nullable|exists:members,id|different:member1_id|different:member2_id',
            'matched_at' => 'required|date',
            'met' => 'boolean',
            'is_current' => 'boolean'
        ]);

        $match = Matches::create($validated);

        return response()->json($match, Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     */
    public function show(Matches $matches): JsonResponse
    {
        return response()->json($matches->load(['member1', 'member2', 'member3']));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Matches $matches)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Matches $match): JsonResponse
    {
        $validated = $request->validate([
            'met' => 'sometimes|boolean',
            'is_current' => 'sometimes|boolean',
            'met_confirmed_at' => 'sometimes|nullable|date'
        ]);

        $match->update($validated);

        return response()->json($match->load(['member1', 'member2', 'member3']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Matches $match): JsonResponse
    {
        $match->delete();

        return response()->json(['message' => 'Match deleted']);
    }

    /**
     * Remove all resources from storage
     */
    public function destroyAll(): JsonResponse
    {
        Matches::query()->delete();
        return response()->json(['message' => 'All matches deleted']);
    }

    /**
     * Trigger matching process for available members.
     */
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
                    'member3' => $match->member3 ? $match->member3->name : null,
                    'matched_at' => $match->matched_at
                ];
            })
        ]);
    }

    /**
     * Mark a match as met.
     */
    public function markAsMet(Matches $match): JsonResponse
    {
        $match->update([
            'met' => true,
            'met_confirmed_at' => now()
        ]);

        $match->member1->metWith()->attach($match->member2->id);
        $match->member2->metWith()->attach($match->member1->id);

        if ($match->member3_id) {
            $match->member1->metWith()->attach($match->member3->id);
            $match->member2->metWith()->attach($match->member3->id);
            $match->member3->metWith()->attach([$match->member1->id, $match->member2->id]);
        }

        return response()->json([
            'message' => 'Match marked as met',
            'match' => $match->load(['member1', 'member2', 'member3'])
        ]);
    }
}

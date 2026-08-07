<?php

namespace App\Http\Controllers;

use App\Models\Festival;
use App\Models\Subscriber;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FestivalController extends Controller
{
    /**
     * Browse page. Data is fetched live by the embedded <livewire:festival-results>
     * component via FestivalSearchService — see plan: Estrategia A (no local DB).
     */
    public function index(Request $request)
    {
        return view('festivals.index');
    }
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'category' => 'nullable|string',
            'genre' => 'nullable|string',
            'country' => 'nullable|string',
        ]);

        $query = Festival::query();

        $query->where(function ($q) use ($request) {
            $q->whereBetween('deadline', [$request->start_date, $request->end_date])
              ->orWhereBetween('opening_date', [$request->start_date, $request->end_date]);
        });

        if ($request->category) {
            $query->where('category', $request->category);
        }

        if ($request->genre) {
            // Escape LIKE wildcards so the user cannot expand the match set.
            $genre = addcslashes((string) $request->genre, '%_\\');
            $query->where('details->genres', 'LIKE', '%' . $genre . '%');
        }

        if ($request->country) {
            $query->where('country', $request->country);
        }

        $festivals = $query->orderBy('deadline')->get();

        return response()->json([
            'festivals' => $festivals,
            'count' => $festivals->count(),
            'filters' => $request->only(['start_date', 'end_date', 'category', 'genre', 'country']),
        ]);
    }

    public function show(Festival $festival)
    {
        $subscriberId = session('subscriber_id');
        $isSubscribed = false;

        if ($subscriberId) {
            $isSubscribed = $festival->subscriptions()
                ->where('subscriber_id', $subscriberId)
                ->exists();
        }

        return view('festivals.show', [
            'festival' => $festival,
            'isSubscribed' => $isSubscribed,
        ]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $request->validate([
            'festival_api_id' => 'required|integer',
            'notification_type' => 'required|in:opening,deadline,both',
        ]);

        $subscriberId = session('subscriber_id');

        if (!$subscriberId) {
            return response()->json(['error' => 'Not registered'], 401);
        }

        $festival = Festival::where('api_id', $request->festival_api_id)->first();

        if (!$festival) {
            return response()->json([
                'error' => 'Festival not found. Sync the catalogue first.',
            ], 404);
        }

        Subscription::updateOrCreate(
            [
                'subscriber_id' => $subscriberId,
                'festival_id' => $festival->id,
            ],
            [
                'notification_type' => $request->notification_type,
            ]
        );

        return response()->json(['success' => true]);
    }

    public function unsubscribe(int $festivalApiId): JsonResponse
    {
        $subscriberId = session('subscriber_id');

        if (!$subscriberId) {
            return response()->json(['error' => 'Not registered'], 401);
        }

        $festival = Festival::where('api_id', $festivalApiId)->first();

        if ($festival) {
            Subscription::where('subscriber_id', $subscriberId)
                ->where('festival_id', $festival->id)
                ->delete();
        }

        return response()->json(['success' => true]);
    }

    public function logout(): JsonResponse
    {
        session()->forget('subscriber_id');

        return response()->json(['success' => true]);
    }
}

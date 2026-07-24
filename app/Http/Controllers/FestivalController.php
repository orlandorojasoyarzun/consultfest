<?php

namespace App\Http\Controllers;

use App\Models\Festival;
use App\Models\Subscriber;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FestivalController extends Controller
{
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
            $query->where('details->genres', 'LIKE', '%' . $request->genre . '%');
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

    public function index(Request $request)
    {
        $query = Festival::query();

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->where(function ($q) use ($request) {
                $q->whereBetween('deadline', [$request->start_date, $request->end_date])
                  ->orWhereBetween('opening_date', [$request->start_date, $request->end_date]);
            });
        }

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('genre')) {
            $query->where('details->genres', 'LIKE', '%' . $request->input('genre') . '%');
        }

        if ($request->has('country')) {
            $query->where('country', $request->country);
        }

        $festivals = $query->orderBy('deadline')->paginate(20);

        return view('festivals.index', compact('festivals'));
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
            $festival = Festival::create([
                'api_id' => $request->festival_api_id,
                'name' => "Festival {$request->festival_api_id}",
            ]);
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

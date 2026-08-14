<?php

namespace App\Http\Controllers;

use App\Data\FestivalData;
use App\Models\Festival;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Notifications\FestivalSubscribedNotification;
use App\Notifications\FestivalUnsubscribedNotification;
use App\Services\FestivalSearchService;
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

    /**
     * Lazy redirect used by the festival list page. When the user clicks a
     * card we run FestivalSearchService::details() (1 FestivalAPI credit,
     * 24h cache per apiId) and 302 to the organizer's real URL. Browsing
     * the list itself never enriches — only signals of intent (clicking)
     * cost credits, and the cache means the same festival costs nothing
     * for the rest of the day.
     *
     * The list-endpoint row has whatever name/URL the API gave us. We
     * build a stub DTO from the cached Festival row when present, else
     * fall back to a stub with just apiId+name so details() can still
     * reach the API and bestUrl() has something to render.
     */
    public function redirectToFestival(int $apiId, FestivalSearchService $search): \Illuminate\Http\RedirectResponse
    {
        // If we have a local Festival row (from dev seeder / sync), use it
        // as the stub so the DTO already has whatever URLs the list gave us
        // — saves us a second API call if bestUrl() already works.
        $local = Festival::where('api_id', $apiId)->first();
        $stub = $local
            ? new FestivalData(
                apiId: $local->api_id ?? $apiId,
                name: $local->name ?? '',
                categories: [],
                primaryCategory: null,
                country: $local->country,
                city: null,
                state: null,
                genres: [],
                deadline: null,
                eventStartDate: null,
                regularFee: null,
                submissionUrl: null,
                website: null,
                compositeScore: null,
            )
            : new FestivalData(
                apiId: $apiId,
                name: '',
                categories: [],
                primaryCategory: null,
                country: null,
                city: null,
                state: null,
                genres: [],
                deadline: null,
                eventStartDate: null,
                regularFee: null,
                submissionUrl: null,
                website: null,
                compositeScore: null,
            );

        $enriched = $search->details($stub);

        $url = $enriched->bestUrl() ?? 'https://filmfreeway.com/';

        return redirect()->away($url);
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

        // Confirmation email — queued. Tells the user the subscription was
        // recorded and reminds them which notification type they chose.
        $subscriber = Subscriber::find($subscriberId);
        if ($subscriber) {
            $subscriber->notify(new FestivalSubscribedNotification(
                $festival,
                $request->notification_type,
            ));
        }

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
            // Only send confirmation email if there WAS a subscription to
            // remove. Idempotent endpoint shouldn't spam users when they
            // hit unsubscribe for a festival they never subscribed to.
            $deleted = Subscription::where('subscriber_id', $subscriberId)
                ->where('festival_id', $festival->id)
                ->delete();

            if ($deleted > 0) {
                $subscriber = Subscriber::find($subscriberId);
                if ($subscriber) {
                    $subscriber->notify(new FestivalUnsubscribedNotification($festival));
                }
            }
        }

        return response()->json(['success' => true]);
    }

    public function logout(): JsonResponse
    {
        session()->forget('subscriber_id');

        return response()->json(['success' => true]);
    }
}

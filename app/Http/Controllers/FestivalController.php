<?php

namespace App\Http\Controllers;

use App\Data\FestivalData;
use App\Models\Festival;
use App\Services\FestivalApiService;
use App\Services\FestivalSearchService;
use App\Services\FestivalSubscriptionService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FestivalController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly FestivalSubscriptionService $subscriptionService,
    ) {
    }

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

        // Logic lives in FestivalSubscriptionService so the Livewire modal
        // (FestivalResults::confirmSubscribe) can run the exact same path
        // without making an HTTP loopback to ourselves. Loopback was
        // deadlocking the PHP session lock for 30s+ before this refactor.
        $result = $this->subscriptionService->subscribe(
            $subscriberId,
            (int) $request->input('festival_api_id'),
            (string) $request->input('notification_type'),
        );

        if (!$result->ok) {
            $status = str_contains((string) $result->error, 'FestivalAPI') ? 404 : 422;
            return response()->json(['error' => $result->error], $status);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Unsubscribe the current subscriber from a festival.
     *
     * Returns JSON for fetch callers (Livewire modal — though the modal
     * uses the service directly) and a redirect-back for plain HTML form
     * submissions (the /festivals/{id} show page still uses a form).
     * Before this split the controller always returned JsonResponse, which
     * dumped raw `{"success":true}` into the browser when a form on
     * /festivals/{id} submitted — the user saw a blank JSON page instead
     * of landing back on the page they were reading. Same controller, two
     * response shapes, decided by Accept header.
     *
     * The actual unsubscribe logic lives in FestivalSubscriptionService so
     * it can be shared with the UnsubscribeFestivalModal Livewire
     * component without an Http::post() loopback (which deadlocks the PHP
     * session lock for 30s+ — same trap subscribe() fell into earlier).
     */
    public function unsubscribe(Request $request, int $festivalApiId): JsonResponse | \Illuminate\Http\RedirectResponse
    {
        $subscriberId = session('subscriber_id');

        if (!$subscriberId) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Not registered'], 401);
            }
            return redirect()->route('auth.login');
        }

        $result = $this->subscriptionService->unsubscribe($subscriberId, $festivalApiId);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }
        return back()->with('auth-flash', $result->message);
    }

    public function logout(): JsonResponse
    {
        session()->forget('subscriber_id');

        return response()->json(['success' => true]);
    }
}

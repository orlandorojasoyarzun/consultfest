<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The post-login landing page. It's a thin read-only view: the user sees
 * the productions and festivals they have attached to their Subscriber.
 *
 * Auth is enforced by `auth.subscriber` middleware (see routes/web.php),
 * so by the time we get here `session('subscriber_id')` is guaranteed
 * to point at a real row.
 */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $subscriberId = (int) session('subscriber_id');

        // Use the same identity we use everywhere else; load the model fresh.
        $subscriber = Subscriber::whereKey($subscriberId)->firstOrFail();

        $productions = $subscriber->productions()
            ->orderByDesc('updated_at')
            ->get();

        $subscriptions = $subscriber->subscriptions()
            ->with('festival')
            ->orderByDesc('created_at')
            ->get();

        return view('dashboard', [
            'subscriber' => $subscriber,
            'productions' => $productions,
            'subscriptions' => $subscriptions,
        ]);
    }
}

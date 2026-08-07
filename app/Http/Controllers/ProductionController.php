<?php

namespace App\Http\Controllers;

use App\Models\Production;
use App\Models\Subscriber;
use App\Services\ProductionMatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductionController extends Controller
{
    public function __construct(private readonly ProductionMatcher $matcher)
    {
    }

    public function index(Request $request): RedirectResponse|View
    {
        $subscriberId = session('subscriber_id');

        if (!$subscriberId || !Subscriber::whereKey($subscriberId)->exists()) {
            // Stale session pointing at a Subscriber that no longer exists
            # (e.g. after migrate:fresh). Clear it and bounce home with a
            # visible message so the user understands what happened.
            session()->forget('subscriber_id');
            session()->flash('production-flash', 'Regístrate para gestionar tus producciones.');
            return redirect()->route('home');
        }

        $productions = Production::where('subscriber_id', $subscriberId)
            ->orderByDesc('updated_at')
            ->get();

        return view('productions.index', [
            'productions' => $productions,
        ]);
    }

    public function create(Request $request): RedirectResponse|View
    {
        $subscriberId = session('subscriber_id');

        if (!$subscriberId || !Subscriber::whereKey($subscriberId)->exists()) {
            session()->forget('subscriber_id');
            session()->flash('production-flash', 'Regístrate para crear producciones.');
            return redirect()->route('home');
        }

        return view('productions.form', [
            'production' => new Production(['subscriber_id' => $subscriberId, 'status' => 'draft']),
        ]);
    }

    public function show(Request $request, Production $production): RedirectResponse|View
    {
        if (!$this->owns($production)) {
            abort(403);
        }

        return view('productions.show', [
            'production' => $production,
        ]);
    }

    public function edit(Request $request, Production $production): RedirectResponse|View
    {
        if (!$this->owns($production)) {
            abort(403);
        }

        return view('productions.form', [
            'production' => $production,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $subscriberId = session('subscriber_id');

        if (!$subscriberId) {
            return redirect()->route('home');
        }

        $data = $this->validatedData($request);
        $data['subscriber_id'] = $subscriberId;

        $production = Production::create($data);

        return redirect()->route('productions.show', $production)
            ->with('production-flash', 'Producción creada.');
    }

    public function update(Request $request, Production $production): RedirectResponse
    {
        if (!$this->owns($production)) {
            abort(403);
        }

        $production->update($this->validatedData($request));

        return redirect()->route('productions.show', $production)
            ->with('production-flash', 'Producción actualizada.');
    }

    public function destroy(Production $production): RedirectResponse
    {
        if (!$this->owns($production)) {
            abort(403);
        }

        $production->delete();

        return redirect()->route('productions.index')
            ->with('production-flash', 'Producción eliminada.');
    }

    public function matches(Request $request, Production $production): RedirectResponse|View
    {
        if (!$this->owns($production)) {
            abort(403);
        }

        $festivals = $this->matcher->matchFor($production);

        return view('productions.matches', [
            'production' => $production,
            'festivals' => $festivals,
        ]);
    }

    /**
     * Validate the production payload. `genres_text` is the user-facing
     * comma-separated string; we split, trim, dedupe, and drop empty values
     * before persisting as a JSON array.
     */
    private function validatedData(Request $request): array
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'synopsis' => 'nullable|string|max:5000',
            'runtime_minutes' => 'nullable|integer|min:1|max:600',
            'format' => 'nullable|in:digital,film,any',
            'country' => 'nullable|string|max:255',
            'production_year' => 'nullable|integer|min:1900|max:2100',
            'category' => 'nullable|in:short_film,feature,documentary,animation,horror,sci_fi,comedy,experimental',
            'genres_text' => 'nullable|string|max:1000',
            'status' => 'nullable|in:draft,active,archived',
        ]);

        $rawGenres = (string) ($validated['genres_text'] ?? '');
        $genres = array_values(array_unique(array_filter(
            array_map('trim', explode(',', $rawGenres)),
            fn ($g) => $g !== ''
        )));

        // Cap to a sane number so the matcher stays performant.
        $genres = array_slice($genres, 0, 20);

        return [
            'title' => $validated['title'],
            'synopsis' => $validated['synopsis'] ?? null,
            'runtime_minutes' => $validated['runtime_minutes'] ?? null,
            'format' => $validated['format'] ?? null,
            'country' => $validated['country'] ?? null,
            'production_year' => $validated['production_year'] ?? null,
            'category' => $validated['category'] ?? null,
            'genres' => $genres,
            'status' => $validated['status'] ?? 'draft',
        ];
    }

    private function owns(Production $production): bool
    {
        $subscriberId = session('subscriber_id');
        return $subscriberId !== null && (int) $production->subscriber_id === (int) $subscriberId;
    }
}
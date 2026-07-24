<?php

use App\Models\Festival;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/festivals', function (Request $request) {
    $query = Festival::query();

    if ($request->has('start_date') && $request->has('end_date')) {
        $query->where(function ($q) use ($request) {
            $q->whereBetween('deadline', [$request->input('start_date'), $request->input('end_date')])
              ->orWhereBetween('opening_date', [$request->input('start_date'), $request->input('end_date')]);
        });
    }

    if ($request->has('category')) {
        $query->where('category', $request->input('category'));
    }

    if ($request->has('country')) {
        $query->where('country', $request->input('country'));
    }

    if ($request->has('genre')) {
        $query->where('details->genres', 'LIKE', '%' . $request->input('genre') . '%');
    }

    if ($request->has('accepting')) {
        $query->where('accepting_submissions', filter_var($request->input('accepting'), FILTER_VALIDATE_BOOLEAN));
    }

    $perPage = min((int) $request->input('per_page', 20), 100);
    $festivals = $query->orderBy('deadline')->paginate($perPage);

    return response()->json([
        'data' => $festivals->items(),
        'meta' => [
            'current_page' => $festivals->currentPage(),
            'last_page' => $festivals->lastPage(),
            'per_page' => $festivals->perPage(),
            'total' => $festivals->total(),
        ],
    ]);
});

Route::get('/festivals/{festival}', function (Festival $festival) {
    return response()->json(['data' => $festival]);
});

Route::get('/festivals/search/by-dates', function (Request $request) {
    $request->validate([
        'start_date' => 'required|date',
        'end_date' => 'required|date|after_or_equal:start_date',
    ]);

    $festivals = Festival::where(function ($q) use ($request) {
        $q->whereBetween('deadline', [$request->input('start_date'), $request->input('end_date')])
          ->orWhereBetween('opening_date', [$request->input('start_date'), $request->input('end_date')]);
    })
    ->orderBy('deadline')
    ->limit(100)
    ->get();

    return response()->json(['data' => $festivals]);
});

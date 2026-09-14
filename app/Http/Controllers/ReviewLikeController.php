<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewLikeController extends Controller
{
    public function __invoke(Request $request, Review $review): RedirectResponse
    {
        $review->likedByUsers()->toggle($request->user()->id);

        return back();
    }
}
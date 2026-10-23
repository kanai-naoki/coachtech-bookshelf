<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Contracts\View\View;

class RankingController extends Controller
{
    /**
     * レビュー平均評価TOP10の書籍ランキングを表示する。
     *
     * @return View
     */
    public function index(): View
    {
        $rankedBooks = Book::ranked(10)->get();

        return view('ranking.index', compact('rankedBooks'));
    }
}

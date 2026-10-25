<?php

namespace App\Http\Controllers;

use App\Services\ReadingReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReadingReportController extends Controller
{
    /**
     * ログインユーザーのマイ読書レポートを表示する。
     *
     * @param  Request  $request  認証済みユーザーを含むリクエスト
     * @param  ReadingReportService  $service  集計サービス
     */
    public function index(Request $request, ReadingReportService $service): View
    {
        $stats = $service->build($request->user());

        return view('reports.index', compact('stats'));
    }
}

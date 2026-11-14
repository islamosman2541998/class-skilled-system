<?php

namespace App\Http\Controllers;

use App\Search\SearchRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request, SearchRegistry $registry): JsonResponse
    {
        return response()->json($registry->search(mb_substr((string) $request->query('q', ''), 0, 100), $request->user()));
    }
}

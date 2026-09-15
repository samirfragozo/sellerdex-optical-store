<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Support\Optics\LensRecommender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LensRecommendationController extends Controller
{
    public function __invoke(Request $request, LensRecommender $recommender): JsonResponse
    {
        $prescription = (array) $request->input('prescription', []);
        $chosen = (array) $request->input('chosen', []);

        return response()->json([
            'recommended' => $recommender->recommend($prescription),
            'warnings' => $recommender->warningsFor($prescription, $chosen),
        ]);
    }
}

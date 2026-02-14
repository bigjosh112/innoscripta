<?php

namespace App\Http\Controllers;

use App\Services\Steps\StepsConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StepsController extends Controller
{
    public function __construct(
        private readonly StepsConfig $stepsConfig
    ) {}

    public function index(Request $request): JsonResponse
    {
        $country = $request->query('country');
        if (empty($country)) {
            return response()->json(['error' => 'country query parameter is required'], 422);
        }

        $steps = $this->stepsConfig->getSteps($country);

        return response()->json(['data' => $steps]);
    }
}

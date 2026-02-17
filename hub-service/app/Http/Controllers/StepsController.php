<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexStepsRequest;
use App\Services\Steps\StepsConfig;
use Illuminate\Http\JsonResponse;

class StepsController extends Controller
{
    public function __construct(
        private readonly StepsConfig $stepsConfig
    ) {}

    public function index(IndexStepsRequest $request): JsonResponse
    {
        $country = $request->validated('country');
        $steps = $this->stepsConfig->getSteps($country);
        return response()->json(['steps' => $steps]);
    }
}

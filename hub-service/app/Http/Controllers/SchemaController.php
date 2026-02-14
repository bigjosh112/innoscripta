<?php

namespace App\Http\Controllers;

use App\Services\Schema\SchemaConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchemaController extends Controller
{
    public function __construct(
        private readonly SchemaConfig $schemaConfig
    ) {}

    public function show(Request $request, string $step_id): JsonResponse
    {
        $country = $request->query('country');
        if (empty($country)) {
            return response()->json(['error' => 'country query parameter is required'], 422);
        }

        $widgets = $this->schemaConfig->getSchema($step_id, $country);

        return response()->json(['data' => ['step_id' => $step_id, 'widgets' => $widgets]]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShowSchemaRequest;
use App\Services\Schema\SchemaConfig;
use Illuminate\Http\JsonResponse;

class SchemaController extends Controller
{
    public function __construct(
        private readonly SchemaConfig $schemaConfig
    ) {}

    public function show(ShowSchemaRequest $request, string $step_id): JsonResponse
    {
        $country = $request->validated('country');
        $widgets = $this->schemaConfig->getSchema($step_id, $country);
        return response()->json(['data' => ['step_id' => $step_id, 'widgets' => $widgets]]);
    }
}

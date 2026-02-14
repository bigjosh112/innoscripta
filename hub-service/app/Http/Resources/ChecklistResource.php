<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class ChecklistResource extends JsonResource
{
    public static ?string $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'overall'   => $this->resource['overall'],
            'employees' => EmployeeChecklistResource::collection(
                collect($this->resource['employees'])
            ),
        ];
    }
}

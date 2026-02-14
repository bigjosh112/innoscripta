<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeChecklistResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->resource['id'],
            'name'                 => $this->resource['name'],
            'checklist'            => $this->resource['checklist'],
            'completion_percentage' => $this->resource['completion_percentage'],
            'complete'             => $this->resource['complete'],
        ];
    }
}

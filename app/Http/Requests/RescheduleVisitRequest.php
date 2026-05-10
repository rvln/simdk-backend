<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RescheduleVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'new_capacity_id'                   => 'required|uuid|exists:capacities,id',
            'updated_items'                     => 'nullable|array',
            'updated_items.*.id'                => 'nullable|uuid',
            'updated_items.*.inventory_id'      => 'nullable|uuid',
            'updated_items.*.itemName_snapshot' => 'required_with:updated_items|string',
            'updated_items.*.qty'               => 'required_with:updated_items|integer|min:1',
        ];
    }
}

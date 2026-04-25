<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitVisitRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'capacity_id'    => 'required|uuid|exists:capacities,id',
            'bringsDonation' => 'sometimes|boolean',
            'donorPhone'     => 'required_if:bringsDonation,true|string|max:20',
            'items'          => 'required_if:bringsDonation,true|array|min:1',
            'items.*.inventory_id' => 'required_with:items|uuid|exists:inventories,id',
            'items.*.qty'          => 'required_with:items|integer|min:1',
        ];
    }
}

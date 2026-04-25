<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitDistributionRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'inventory_id' => 'required|uuid|exists:inventories,id',
            'qty' => 'required|integer|min:1',
        ];
    }
}

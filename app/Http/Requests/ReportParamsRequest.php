<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportParamsRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'type' => 'nullable|string|in:donation,visit,distribution',
            'export' => 'nullable|boolean',
        ];
    }
}

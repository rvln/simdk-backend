<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\DonationTypeEnum;
use Illuminate\Validation\Rule;

class InitiateDonationRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'donorName' => 'required|string|max:255',
            'donorEmail' => 'required|email|max:255',
            'donorPhone' => 'required|string|max:20',
            'amount' => 'required|numeric|min:10000',
        ];
    }
}

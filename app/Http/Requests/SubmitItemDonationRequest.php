<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitItemDonationRequest extends FormRequest
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
            'inventory_id' => 'required|uuid|exists:inventories,id',
            'itemName_snapshot' => 'required|string|max:255',
            'qty' => 'required|integer|min:1',
            // Typically there would be a 'foto' or 'image' rule here too
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\InventoryEnum;
use Illuminate\Validation\Rules\Enum;

class UpdateInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Role gate is in the controller
    }

    public function rules(): array
    {
        return [
            'itemName'    => ['sometimes', 'required', 'string', 'max:255'],
            'category'    => ['sometimes', 'required', 'string', new Enum(InventoryEnum::class)],
            'target_qty'  => ['sometimes', 'required', 'integer', 'min:1'],
            'unit'        => ['sometimes', 'required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'itemName.required'   => 'Nama barang wajib diisi.',
            'category.*'          => 'Kategori tidak valid. Nilai yang diizinkan: MAKANAN, PAKAIAN, PENDIDIKAN, KESEHATAN, KEBERSIHAN, LAINNYA.',
            'target_qty.min'      => 'Jumlah target minimal adalah 1.',
            'unit.required'       => 'Satuan wajib diisi.',
        ];
    }
}

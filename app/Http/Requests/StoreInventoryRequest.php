<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\InventoryEnum;
use Illuminate\Validation\Rules\Enum;

class StoreInventoryRequest extends FormRequest
{
    /**
     * Only PENGURUS_PANTI and KEPALA_PANTI may create inventory catalog items.
     * AGENTS.md §3 — Role authorization is enforced at the Service/Controller gate.
     */
    public function authorize(): bool
    {
        return true; // Role gate is in the controller
    }

    public function rules(): array
    {
        return [
            'itemName'    => ['required', 'string', 'max:255'],
            'category'    => ['required', 'string', new Enum(InventoryEnum::class)],
            'target_qty'  => ['required', 'integer', 'min:1'],
            'unit'        => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'itemName.required'   => 'Nama barang wajib diisi.',
            'category.required'   => 'Kategori wajib dipilih.',
            'category.*'          => 'Kategori tidak valid. Nilai yang diizinkan: MAKANAN, PAKAIAN, PENDIDIKAN, KESEHATAN, KEBERSIHAN, LAINNYA.',
            'target_qty.required' => 'Jumlah target wajib diisi.',
            'target_qty.min'      => 'Jumlah target minimal adalah 1.',
            'unit.required'       => 'Satuan wajib diisi.',
        ];
    }
}

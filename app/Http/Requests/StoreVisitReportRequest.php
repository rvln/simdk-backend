<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVisitReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in Controller via role + ownership check
    }

    public function rules(): array
    {
        return [
            'visit_id' => ['required', 'uuid', 'exists:visits,id'],
            'content'  => ['required', 'string', 'min:10', 'max:5000'],
            'images'   => ['nullable', 'array', 'max:5'],
            'images.*' => ['image', 'mimes:jpeg,png,jpg', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'visit_id.required'  => 'ID kunjungan wajib diisi.',
            'visit_id.exists'    => 'Kunjungan tidak ditemukan.',
            'content.required'   => 'Konten laporan wajib diisi.',
            'content.min'        => 'Konten laporan minimal 10 karakter.',
            'content.max'        => 'Konten laporan maksimal 5000 karakter.',
            'images.max'         => 'Maksimal 5 gambar per laporan.',
            'images.*.mimes'     => 'Format gambar harus jpeg, png, atau jpg.',
            'images.*.max'       => 'Ukuran gambar maksimal 2048 KB (2 MB).',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportDecRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:5120',
                'mimetypes:text/plain,application/octet-stream',
                function (string $attribute, $value, $fail) {
                    $extension = strtolower((string) $value->getClientOriginalExtension());

                    if ($extension !== 'dec') {
                        $fail('O arquivo deve ter extensão .DEC.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Envie um arquivo .DEC.',
            'file.file' => 'Envie um arquivo válido.',
            'file.max' => 'O arquivo não pode ultrapassar 5MB.',
            'file.mimetypes' => 'Formato inválido. Envie um arquivo texto .DEC.',
        ];
    }
}

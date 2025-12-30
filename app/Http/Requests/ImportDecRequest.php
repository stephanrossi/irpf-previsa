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
            'files' => ['required', 'array', 'min:1'],
            'files.*' => [
                'required',
                'file',
                'max:5120',
                'mimetypes:text/plain,application/octet-stream',
                function (string $attribute, $value, $fail) {
                    $extension = strtolower((string) $value?->getClientOriginalExtension());

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
            'files.required' => 'Envie pelo menos um arquivo .DEC.',
            'files.array' => 'Envie arquivos válidos.',
            'files.min' => 'Selecione pelo menos um arquivo.',
            'files.*.required' => 'Arquivo obrigatório.',
            'files.*.file' => 'Envie arquivos válidos.',
            'files.*.max' => 'Cada arquivo não pode ultrapassar 5MB.',
            'files.*.mimetypes' => 'Formato inválido. Envie um arquivo texto .DEC.',
        ];
    }
}

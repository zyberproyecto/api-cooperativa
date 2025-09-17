<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSolicitudRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $menores = $this->input('menores_a_cargo');
        if (is_string($menores)) {
            $menores = strtolower(trim($menores));
            $menores = in_array($menores, ['si','sí','true','1'], true) ? 1 : 0;
            $this->merge(['menores_a_cargo' => (bool)$menores]);
        }
        if (!$this->filled('dormitorios') && $this->filled('intereses')) {
            if (preg_match('/\d+/', (string)$this->input('intereses'), $m)) {
                $this->merge(['dormitorios' => (int)$m[0]]);
            }
        }
        
        if (!$this->filled('ci') && $this->filled('ci_usuario')) {
            $this->merge(['ci' => $this->input('ci_usuario')]);
        }
    }

    public function rules(): array
    {
        return [
            'ci'               => ['required_without:ci_usuario','string','max:20'],
            'ci_usuario'       => ['sometimes','string','max:20'],
            'nombre_completo'  => ['required','string','max:150'],
            'email'            => ['required','email','max:150'],
            'telefono'         => ['required','string','max:50'],
            'menores_a_cargo'  => ['required','boolean'],
            'dormitorios'      => ['required','integer','min:0','max:10'],
            'comentarios'      => ['nullable','string','max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'ci.required_without' => 'Debes indicar la cédula.',
        ];
    }
}
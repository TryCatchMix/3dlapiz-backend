<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // La autorización se maneja en el middleware de autenticación
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'first_name' => [
                'sometimes',
                'string',
                'min:2',
                'max:50',
                'regex:/^[\p{L}\s\-\'\.]+$/u' // Solo letras, espacios, guiones y apostrofes
            ],
            'last_name' => [
                'sometimes',
                'string',
                'min:2',
                'max:50',
                'regex:/^[\p{L}\s\-\'\.]+$/u'
            ],
            'email' => [
                'sometimes',
                'email:rfc,dns',
                'max:255',
                Rule::unique('users')->ignore($this->user()->id)->whereNull('deleted_at'),
            ],

            'phone_country_code' => [
                'required_with:phone_number',
                'string',
                'max:5',
                'regex:/^\+?[1-9]\d{0,3}$/' // Códigos de país válidos
            ],
            'phone_number' => [
                'nullable',
                'string',
                'max:20',
                'min:7',
                'regex:/^[0-9\s\-\(\)]+$/' // Solo números, espacios, guiones y paréntesis
            ],

            'street' => [
                'nullable',
                'string',
                'max:255',
                'min:5',
                'regex:/^[\p{L}\p{N}\s\-\,\.\/\#]+$/u' // Letras, números y caracteres comunes de direcciones
            ],
            'city' => [
                'nullable',
                'string',
                'max:100',
                'min:2',
                'regex:/^[\p{L}\s\-\'\.]+$/u' // Solo letras, espacios y caracteres comunes de ciudades
            ],
            'state' => [
                'nullable',
                'string',
                'max:100',
                'min:2',
                'regex:/^[\p{L}\s\-\'\.]+$/u'
            ],
            'postal_code' => [
                'nullable',
                'string',
                'max:20',
                'min:3',
                'regex:/^[A-Z0-9\s\-]+$/i' // Códigos postales alfanuméricos
            ],
            'country_code' => [
                'nullable',
                'string',
                'size:2',
                'uppercase',
                'regex:/^[A-Z]{2}$/' // Códigos ISO de país de 2 letras
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'first_name.regex' => 'El nombre solo puede contener letras, espacios, guiones y apostrofes.',
            'first_name.min' => 'El nombre debe tener al menos 2 caracteres.',
            'first_name.max' => 'El nombre no puede tener más de 50 caracteres.',

            'last_name.regex' => 'El apellido solo puede contener letras, espacios, guiones y apostrofes.',
            'last_name.min' => 'El apellido debe tener al menos 2 caracteres.',
            'last_name.max' => 'El apellido no puede tener más de 50 caracteres.',

            'email.email' => 'El correo electrónico debe ser una dirección válida.',
            'email.unique' => 'Este correo electrónico ya está en uso.',
            'email.max' => 'El correo electrónico no puede tener más de 255 caracteres.',

            'phone_country_code.required_with' => 'El código de país es requerido cuando se proporciona un número de teléfono.',
            'phone_country_code.regex' => 'El código de país debe ser un código válido (ej: +34, +1).',
            'phone_country_code.max' => 'El código de país no puede tener más de 5 caracteres.',

            'phone_number.regex' => 'El número de teléfono solo puede contener números, espacios, guiones y paréntesis.',
            'phone_number.min' => 'El número de teléfono debe tener al menos 7 dígitos.',
            'phone_number.max' => 'El número de teléfono no puede tener más de 20 caracteres.',

            'street.regex' => 'La dirección contiene caracteres no válidos.',
            'street.min' => 'La dirección debe tener al menos 5 caracteres.',
            'street.max' => 'La dirección no puede tener más de 255 caracteres.',

            'city.regex' => 'El nombre de la ciudad solo puede contener letras, espacios y caracteres comunes.',
            'city.min' => 'El nombre de la ciudad debe tener al menos 2 caracteres.',
            'city.max' => 'El nombre de la ciudad no puede tener más de 100 caracteres.',

            'state.regex' => 'El nombre del estado/provincia solo puede contener letras, espacios y caracteres comunes.',
            'state.min' => 'El nombre del estado/provincia debe tener al menos 2 caracteres.',
            'state.max' => 'El nombre del estado/provincia no puede tener más de 100 caracteres.',

            'postal_code.regex' => 'El código postal solo puede contener letras, números, espacios y guiones.',
            'postal_code.min' => 'El código postal debe tener al menos 3 caracteres.',
            'postal_code.max' => 'El código postal no puede tener más de 20 caracteres.',

            'country_code.size' => 'El código de país debe tener exactamente 2 letras.',
            'country_code.regex' => 'El código de país debe ser un código ISO válido de 2 letras.',
            'country_code.uppercase' => 'El código de país debe estar en mayúsculas.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'first_name' => 'nombre',
            'last_name' => 'apellido',
            'email' => 'correo electrónico',
            'phone_country_code' => 'código de país',
            'phone_number' => 'número de teléfono',
            'street' => 'dirección',
            'city' => 'ciudad',
            'state' => 'estado/provincia',
            'postal_code' => 'código postal',
            'country_code' => 'código de país',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('phone_number')) {
            $this->merge([
                'phone_number' => preg_replace('/[^0-9]/', '', $this->phone_number)
            ]);
        }

        if ($this->has('postal_code')) {
            $this->merge([
                'postal_code' => strtoupper(trim($this->postal_code))
            ]);
        }

        if ($this->has('country_code')) {
            $this->merge([
                'country_code' => strtoupper(trim($this->country_code))
            ]);
        }

        $textFields = ['first_name', 'last_name', 'street', 'city', 'state'];
        foreach ($textFields as $field) {
            if ($this->has($field)) {
                $this->merge([
                    $field => trim(preg_replace('/\s+/', ' ', $this->{$field}))
                ]);
            }
        }
    }
}

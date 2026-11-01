<?php

namespace App\Http\Requests\Profile;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function prepareForValidation(): void
    {
        $gender = $this->input('gender');

        if (is_string($gender)) {
            $this->merge([
                'gender' => strtoupper(trim($gender)),
            ]);
        }
    }
    public function rules(): array
    {
        return [
            'full_name' => 'sometimes|required|string|max:255',
            'avatar' => 'sometimes|nullable|string|max:255',
            'phone' => 'sometimes|nullable|string|regex:/^[0-9]{10,11}$/',
            'date_of_birth' => 'sometimes|nullable|date_format:Y-m-d|before:today',
            'gender' => [
                'sometimes',
                'nullable',
                'string',
                'in:MALE,FEMALE,OTHER',
            ],
            'address' => 'sometimes|nullable|string|max:500',
        ];
    }
}

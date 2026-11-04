<?php

namespace App\Http\Requests\Trip;

use Illuminate\Foundation\Http\FormRequest;

class StoreTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'destination' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date', 'before:end_date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'budget' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
                'decimal:0,2',
                'max:9999999999999.99',
            ],
            'currency' => ['nullable', 'string', 'max:10'],
        ];
    }
}

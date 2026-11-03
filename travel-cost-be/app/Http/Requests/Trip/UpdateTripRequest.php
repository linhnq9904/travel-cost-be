<?php

namespace App\Http\Requests\Trip;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'destination' => ['sometimes', 'required', 'string', 'max:255'],
            'start_date' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'end_date' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'budget' => ['sometimes', 'required', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'required', 'string', 'max:10'],
            'status' => [
                'sometimes',
                'required',
                'in:Planning,Ongoing,Finished',
            ],
        ];
    }
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if (!$this->hasAny(['start_date', 'end_date'])) {
                    return;
                }

                $trip = $this->route('trip');

                $startDate = $this->input(
                    'start_date',
                    $trip->start_date->format('Y-m-d')
                );

                $endDate = $this->input(
                    'end_date',
                    $trip->end_date->format('Y-m-d')
                );

                if ($endDate < $startDate) {
                    $field = $this->has('end_date')
                        ? 'end_date'
                        : 'start_date';

                    $validator->errors()->add(
                        $field,
                        'The end date must be on or after the start date.'
                    );
                }
            },
        ];
    }
}

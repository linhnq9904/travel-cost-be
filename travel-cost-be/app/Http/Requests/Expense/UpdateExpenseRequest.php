<?php

namespace App\Http\Requests\Expense;

use App\Models\TripMember;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $tripId = $this->route('tripId');
        $userId = $this->user()->id;

        return TripMember::where('trip_id', $tripId)
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tripId = $this->route('tripId');
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'category_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('expense_categories', 'id')
                    ->where('trip_id', $tripId),
            ],

            'paid_by' => [
                'sometimes',
                'required',
                'uuid',
                Rule::exists('trip_members', 'user_id')
                    ->where('trip_id', $tripId),
            ],
            'amount' => ['sometimes', 'required', 'numeric', 'gt:0', 'decimal:0,2'],
            'expense_date' => ['sometimes', 'required', 'date'],
        ];
    }
}

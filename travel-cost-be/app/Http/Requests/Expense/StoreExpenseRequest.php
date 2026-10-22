<?php

namespace App\Http\Requests\Expense;

use App\Models\TripMember;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
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
            'category_id' => [
                'required',
                'integer',
                Rule::exists('expense_categories', 'id')
                    ->where('trip_id', $tripId),
            ],
            'description' => 'nullable|string|max:255',
            'title' => 'required|string|max:225',
            'amount' => 'required|numeric|gt:0|decimal:0,2|max:9999999999.99',
            'expense_date' => 'required|date',
            'paid_by' => [
                'required',
                'uuid',
                Rule::exists('trip_members', 'user_id')
                    ->where('trip_id', $tripId)
            ]
        ];
    }
}

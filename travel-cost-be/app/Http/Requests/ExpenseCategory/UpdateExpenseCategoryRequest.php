<?php

namespace App\Http\Requests\ExpenseCategory;

use App\Models\ExpenseCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $tripId = $this->route('tripId');
        $categoryId = $this->route('categoryId');
        $category = ExpenseCategory::where('id', $categoryId)->where('trip_id', $tripId)->firstOrFail();

        return $this->user()->can('update', $category);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {

        $tripId = $this->route('tripId');
        $category = ExpenseCategory::where('id', $this->route('categoryId'))
            ->where('trip_id', $tripId)
            ->firstOrFail();

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('expense_categories', 'name')
                    ->where('trip_id', $tripId)
                    ->ignore($category),
            ],
            'icon' => 'sometimes|nullable|string|max:100',
            'color' => 'sometimes|nullable|string|max:20',
        ];
    }
}

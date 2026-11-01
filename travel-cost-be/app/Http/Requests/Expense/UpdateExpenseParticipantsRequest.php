<?php

namespace App\Http\Requests\Expense;

use App\Models\TripMember;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;


class UpdateExpenseParticipantsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $tripId = $this->route("tripId");
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
    protected function prepareForValidation(): void
    {
        $rows = $this->input('participants');

        if (!is_array($rows)) {
            return;
        }

        foreach ($rows as $index => $row) {
            if (
                is_array($row)
                && isset($row['user_id'])
                && is_string($row['user_id'])
            ) {
                $rows[$index]['user_id'] =
                    strtolower(trim($row['user_id']));
            }
        }

        $this->merge(['participants' => $rows]);
    }
    public function rules(): array
    {
        $tripId = $this->route("tripId");

        return [
            'split_type' => [
                'required',
                Rule::in(['Equal', 'Percentage', 'Fixed']),
            ],

            'participants' => ['required', 'array', 'min:1'],

            'participants.*' => ['required', 'array:user_id,share_percent,share_amount'],

            'participants.*.user_id' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists('trip_members', 'user_id')
                    ->where('trip_id', $tripId),
            ],
            'participants.*.share_percent' => [
                'exclude_unless:split_type,Percentage',
                'required',
                'numeric',
                'decimal:0,2',
                'gt:0',
                'max:100',
            ],

            'participants.*.share_amount' => [
                'exclude_unless:split_type,Fixed',
                'required',
                'numeric',
                'decimal:0,2',
                'gt:0',
                'max:9999999999.99',
            ],
        ];
    }
}

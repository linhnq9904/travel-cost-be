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
    public function rules(): array
    {
        $tripId = $this->route("tripId");

        return [
            'participants' => ['required', 'array', 'min:1'],

            'participants.*' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists('trip_members', 'user_id')
                    ->where('trip_id', $tripId),
            ],
        ];
    }
}

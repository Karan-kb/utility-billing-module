<?php

namespace App\Http\Requests\AccountGroup;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\MainGroup;
use App\Models\SubGroup;
use App\Models\AccountGroup;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{

   public function authorize()
{
    return true;
}
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:50',
                function ($attribute, $value, $fail) {
                    $exists = AccountGroup::where('name', $value)
                        ->whereNull('deleted_at')
                        ->exists();
                    if ($exists) {
                        $fail('The name has already been taken.');
                    }
                },
            ],
            'is_active' => 'required|boolean',
            'sub_group_id' => [
                'nullable',
                'integer',
                function ($attribute, $value, $fail) {
                    if (!SubGroup::where('id', $value)->exists()) {
                        $fail('The selected sub group is invalid.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Name field is required.',
            'name.max' => 'Name cannot exceed 50 characters.',
            'is_active.required' => 'is_active field is required.',
            'is_active.boolean' => 'is_active must be true or false.',
        ];
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        $errors = $validator->errors()->toArray();
        $firstMessage = reset($errors)[0] ?? 'Validation error';

        $response = response()->json([
            'message' => $firstMessage,
            'errors' => $errors,
        ], 422);

        throw new \Illuminate\Validation\ValidationException($validator, $response);
    }
}

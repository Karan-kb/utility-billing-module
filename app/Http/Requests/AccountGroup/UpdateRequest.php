<?php

namespace App\Http\Requests\AccountGroup;

use App\Models\AccountGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\MainGroup;
use App\Models\SubGroup;

class UpdateRequest extends FormRequest
{
    public function authorize()
{
    return true;
}
    public function rules(): array
    {
        $id = $this->route('id');

        return [

            'name' => [
                'required',
                'string',
                'max:50',
                function ($attribute, $value, $fail) use ($id) {
                    if (
                        AccountGroup::on('tenant')
                            ->where('name', $value)
                            ->whereNull('deleted_at')
                            ->where('id', '!=', $id)
                            ->exists()
                    ) {
                        $fail('The name has already been taken.');
                    }
                },
            ],
            'is_active' => 'required|boolean',

            'sub_group_id' => [
                'nullable',
                'integer',
                function ($attribute, $value, $fail) {
                    if ($value !== null &&
                        !SubGroup::on('tenant')->where('id', $value)->exists()) {   // 🔥 Tenant
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

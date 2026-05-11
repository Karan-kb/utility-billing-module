<?php

namespace App\Http\Requests\AccountHead;

use App\Models\AccountHead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\AccountGroup;

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
                        AccountHead::on('tenant')
                            ->where('name', $value)
                            ->whereNull('deleted_at')
                            ->where('id', '!=', $id)
                            ->exists()
                    ) {
                        $fail('The name has already been taken.');
                    }
                },
            ],


            'name_np' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($id) {
                    if (
                        AccountHead::on('tenant')
                            ->where('name', $value)
                            ->whereNull('deleted_at')
                            ->where('id', '!=', $id)
                            ->exists()
                    ) {
                        $fail('The name in nepali has already been taken.');
                    }
                },
            ],

            'is_active' => ['required', 'boolean'],

            'account_group_id' => [
                'nullable',
                'integer',
                function ($attribute, $value, $fail) {
                    if ($value && !AccountGroup::on('tenant')->where('id', $value)->exists()) {
                        $fail('The selected account group is invalid.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The name field is required.',
            'name_np.required' => 'The Nepali name field is required.',
            'name.max' => 'Name cannot exceed 50 characters.',
            'is_active.required' => 'is_active field is required.',
            'is_active.boolean' => 'is_active must be true or false.',
        ];
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        $errors = $validator->errors()->toArray();
        $firstMessage = reset($errors)[0] ?? 'Validation error';

        throw new \Illuminate\Validation\ValidationException(
            $validator,
            response()->json([
                'message' => $firstMessage,
                'errors' => $errors,
            ], 422)
        );
    }
}

<?php

namespace App\Http\Requests\MainGroup;

use App\Models\MainGroup;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permission handled via middleware
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id'); // fetch ID from route

        return [
            'name' => [
                'required',
                'string',
                'max:50',
                function ($attribute, $value, $fail) use ($id) {
                    if (
                        MainGroup::on('tenant')
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

    /**
     * Force JSON response for validation errors with first message as top-level "message"
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        $errors = $validator->errors()->toArray();
        $firstMessage = count($errors) ? reset($errors)[0] : 'Validation error';

        $response = response()->json([
            'message' => $firstMessage,
            'errors' => $errors,
        ], 422);

        throw new \Illuminate\Validation\ValidationException($validator, $response);
    }

}

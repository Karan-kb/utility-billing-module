<?php

namespace App\Http\Requests\MainGroup;

use App\Models\MainGroup;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
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
                    $exists = MainGroup::on('tenant')
                        ->where('name', $value)
                        ->whereNull('deleted_at')
                        ->exists();

                    if ($exists) {
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
}

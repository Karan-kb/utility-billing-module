<?php

namespace App\Http\Requests\SubGroup;

use App\Models\SubGroup;
use App\Models\MainGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasOrganizationPermission('create sub groups');
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:50',
                function ($attribute, $value, $fail) {
                    if (SubGroup::where('name', $value)->whereNull('deleted_at')->exists()) {
                        $fail('The name has already been taken.');
                    }
                }
            ],
            'is_active' => 'required|boolean',
            'main_group_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) {
                    if (!MainGroup::where('id', $value)->exists()) {
                        $fail('The selected main group is invalid.');
                    }
                }
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
            'main_group_id.required' => 'Main group field is required.',
        ];
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        $errors = $validator->errors()->toArray();
        $firstMessage = count($errors) ? reset($errors)[0] : 'Validation error';

        $response = response()->json([
            'message' => $firstMessage,
            'errors' => $errors
        ], 422);

        throw new ValidationException($validator, $response);
    }
}

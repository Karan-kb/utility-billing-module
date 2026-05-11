<?php

namespace App\Http\Requests\OtherIncomeSetup;

use App\Models\AccountHead;
use App\Models\OtherIncomeSetup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $accountHeadId = $this->route('id'); // Use 'id' since route param is {id}

        return [
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('tenant.account_heads')->ignore($accountHeadId)->whereNull('deleted_at'),
            ],
            'name_np' => [
                'required',
                'string',
                'max:50',
                Rule::unique('tenant.account_heads')->ignore($accountHeadId)->whereNull('deleted_at'),
            ],
            'amount' => [
                'nullable',
                'numeric'
                
            ]

        ];
    }

    public function messages(): array
    {
        return [

            'name.required' => 'English name is required.',
            'name.max' => 'English name cannot exceed 50 characters.',
            'name.unique' => 'English name already exists.',


            'name_np.required' => 'Nepali name is required.',
            'name_np.max' => 'Nepali name cannot exceed 50 characters.',
            'name_np.unique' => 'Nepali name already exists.',


        ];
    }


    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'error' => 'Validation failed',
            'messages' => $validator->errors(),
            'status' => 422,
        ], 422));
    }

}

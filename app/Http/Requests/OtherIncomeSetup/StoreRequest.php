<?php

namespace App\Http\Requests\OtherIncomeSetup;

use App\Models\AccountHead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class StoreRequest extends FormRequest
{


    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('tenant.account_heads')->whereNull('deleted_at'),
            ],
            'name_np' => [
                'required',
                'string',
                'max:50',
                Rule::unique('tenant.account_heads')->whereNull('deleted_at'),
            ],
            'amount' => [
                'nullable',
                'numeric',
                'gt:0',
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
        $allErrors = $validator->errors(); // all errors
        $firstErrorMessage = collect($allErrors->all())->first(); // first error message

        throw new HttpResponseException(response()->json([
            'message' => $firstErrorMessage,
            'errors' => $allErrors,
        ], 422));
    }


}

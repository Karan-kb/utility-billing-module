<?php

namespace App\Http\Requests;

use App\Models\WiringPerson;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Or add your permission logic
    }

    public function rules(): array
    {
        return [
            'wiring_person_name' => 'required|string|max:100',
            'wiring_person_no' => [
                'required',
                'string',
                'max:10',
                function ($attribute, $value, $fail) {
                    $exists = WiringPerson::where('wiring_person_no', $value)
                        ->whereNull('deleted_at') 
                        ->exists();

                    if ($exists) {
                        $fail("The {$attribute} has already been taken.");
                    }
                }
            ],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        $errors = $validator->errors();

        $firstMessage = $errors->first();

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $firstMessage, // <-- Use first validation error
            'errors' => $errors
        ], 422));
    }
}

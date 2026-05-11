<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Models\WiringPerson;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Or add your permission logic
    }

    public function rules(): array
    {
        // Get ID from route parameter
        $wiringPersonId = $this->route('wiringPersonId');

        return [
            'wiring_person_name' => 'sometimes|required|string|max:100',
            'wiring_person_no' => [
                'nullable',
                'string',
                'max:10',
                Rule::unique(WiringPerson::class, 'wiring_person_no')
                    ->ignore($wiringPersonId) // ignore current record
                    ->whereNull('deleted_at'), // soft delete
            ],
        ];
    }



    protected function failedValidation(Validator $validator)
    {
        $errors = $validator->errors();
        $firstMessage = $errors->first();

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $firstMessage,
            'errors' => $errors
        ], 422));
    }
}

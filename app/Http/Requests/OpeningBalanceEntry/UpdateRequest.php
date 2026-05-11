<?php

namespace App\Http\Requests\OpeningBalanceEntry;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'entries' => 'required|array|min:1',
            'entries.*.account_head_id' => 'required|exists:tenant.account_heads,id',
            'entries.*.particulars' => 'nullable|string|max:255',
            'entries.*.debit' => 'nullable|numeric|min:0',
            'entries.*.credit' => 'nullable|numeric|min:0',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure entries is always an array, even if null is passed
        if ($this->has('entries') && $this->entries === null) {
            $this->merge(['entries' => []]);
        }
        
        // If entries doesn't exist in request, set default empty array
        if (!$this->has('entries')) {
            $this->merge(['entries' => []]);
        }
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'entries.required' => 'Entries are required.',
            'entries.array' => 'Entries must be an array.',
            'entries.min' => 'At least one entry is required.',
            'entries.*.account_head_id.required' => 'Account head is required for each entry.',
            'entries.*.account_head_id.exists' => 'Selected account head does not exist.',
            'entries.*.debit.numeric' => 'Debit must be a number.',
            'entries.*.credit.numeric' => 'Credit must be a number.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $entries = $this->input('entries', []);
            
            if (empty($entries)) {
                $validator->errors()->add('entries', 'Entries cannot be empty.');
                return;
            }

            $totalDebit = 0;
            $totalCredit = 0;
            
            foreach ($entries as $index => $entry) {
                $debit = isset($entry['debit']) ? (float) $entry['debit'] : 0;
                $credit = isset($entry['credit']) ? (float) $entry['credit'] : 0;
                
                // Check if both debit and credit are provided or both are zero
                if (($debit > 0 && $credit > 0) || ($debit == 0 && $credit == 0)) {
                    $validator->errors()->add(
                        "entries.{$index}", 
                        "Each entry must have either debit OR credit (not both or none)."
                    );
                }
                
                $totalDebit += $debit;
                $totalCredit += $credit;
            }
            
            // Check if totals match
            if (round($totalDebit, 2) !== round($totalCredit, 2)) {
                $validator->errors()->add(
                    'entries', 
                    'Total Debit and Total Credit must be equal.'
                );
            }
        });
    }
}
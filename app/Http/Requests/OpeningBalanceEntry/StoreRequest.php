<?php

namespace App\Http\Requests\OpeningBalanceEntry;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\OpeningBalanceEntry;
use App\Helpers\Helper;

class StoreRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'entries' => 'required|array|min:1',
            'entries.*.account_head_id' => 'required|exists:tenant.account_heads,id',
            'entries.*.debit' => 'nullable|numeric|min:0',
            'entries.*.credit' => 'nullable|numeric|min:0',
            'entries.*.particulars' => 'nullable|string|max:255',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $entries = $this->input('entries', []);

            $totalDebit = 0;
            $totalCredit = 0;

            foreach ($entries as $index => $entry) {
                $debit = isset($entry['debit']) ? (float)$entry['debit'] : 0;
                $credit = isset($entry['credit']) ? (float)$entry['credit'] : 0;

                if (($debit > 0 && $credit > 0) || ($debit == 0 && $credit == 0)) {
                    $validator->errors()->add(
                        "entries.$index",
                        'Each entry must have either debit or credit (not both or empty).'
                    );
                }

                $totalDebit += $debit;
                $totalCredit += $credit;
            }

            if (round($totalDebit, 2) !== round($totalCredit, 2)) {
                $validator->errors()->add(
                    'entries',
                    'Total debit and total credit must be equal.'
                );
            }

            // Check if opening balance already exists
            $fiscalYearId = Helper::getActiveFiscalYearId();
            if ($fiscalYearId && OpeningBalanceEntry::where('fiscal_year_id', $fiscalYearId)->exists()) {
                $validator->errors()->add(
                    'entries',
                    'Opening balance already exists for this fiscal year.'
                );
            }
        });
    }

    public function messages()
    {
        return [
            'entries.required' => 'At least one entry is required.',
            'entries.array' => 'Entries must be an array.',
            'entries.*.account_head_id.required' => 'Account head is required for each entry.',
            'entries.*.account_head_id.exists' => 'Selected account head does not exist.',
            'entries.*.debit.numeric' => 'Debit must be a numeric value.',
            'entries.*.debit.min' => 'Debit cannot be negative.',
            'entries.*.credit.numeric' => 'Credit must be a numeric value.',
            'entries.*.credit.min' => 'Credit cannot be negative.',
            'entries.*.particulars.max' => 'Particulars cannot exceed 255 characters.',
        ];
    }
}
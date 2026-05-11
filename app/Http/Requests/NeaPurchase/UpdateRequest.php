<?php

namespace App\Http\Requests\NeaPurchase;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;
use App\Models\NEAPurchase;
use App\Models\NEAPaymentEntry;

class UpdateRequest extends FormRequest
{
    // public function authorize(): bool
    // {
    //     return true;
    // }

    public function rules()
    {
        return [
            'total_units' => ['required', 'numeric', 'gt:0'],
            'amount'      => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
        ];
    }

    /**
     * Only allow validation of editable fields.
     * Extra fields like date_in_bs, transformer_id, month are ignored,
     * but if transformer_id or month are present and changed, throw error.
     */
    protected function prepareForValidation()
    {
        $allowed = ['total_units', 'amount'];
        $input = array_keys($this->all());

        // Check for forbidden changes
        $purchase = NeaPurchase::findOrFail($this->route('id'));

        if (isset($this->transformer_id) && $this->transformer_id != $purchase->transformer_id) {
            throw ValidationException::withMessages([
                'transformer_id' => ['Transformer cannot be changed.']
            ]);
        }

        if (isset($this->month) && $this->month != $purchase->month) {
            throw ValidationException::withMessages([
                'month' => ['Month cannot be changed.']
            ]);
        }

        // Only validate allowed fields
        $input = array_keys($this->only($allowed));

        if (empty($input)) {
            throw ValidationException::withMessages([
                'fields' => ['No editable fields were provided.']
            ]);
        }
    }

    /**
     * Check if the NEA purchase already has payments.
     * If yes, block the update.
     */
    protected function passedValidation()
    {
        $purchase = NeaPurchase::findOrFail($this->route('id'));

        $hasPayment = NEAPaymentEntry::on('tenant')
            ->where('transformer_id', $purchase->transformer_id)
            ->where('month', $purchase->month)
            ->whereNull('deleted_at')
            ->exists();

        if ($hasPayment) {
            throw ValidationException::withMessages([
                'payment' => ['Cannot edit a purchase that already has a payment.']
            ]);
        }
    }

    /**
     * Customize the failed validation JSON response.
     */
    protected function failedValidation(Validator $validator)
    {
        $errors = $validator->errors()->toArray();
        $firstMessage = collect($errors)->flatten()->first() ?? 'Validation error';

        throw new ValidationException(
            $validator,
            response()->json([
                'message' => $firstMessage,
                'errors'  => $errors,
            ], 422)
        );
    }
}

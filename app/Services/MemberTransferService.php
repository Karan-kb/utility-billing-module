<?php
namespace App\Services;

use App\Models\MemberEntry;
use App\Models\MeterIssue;
use App\Models\MeterDepositTransaction;
use App\Models\ShareTransaction;
use App\Models\Payment;
use App\Models\NameTransferEntry;
use App\Models\OpeningMeterDepositEntry;
use App\Models\ShareOpeningEntry;
use Illuminate\Support\Facades\DB;

class MemberTransferService
{
    public function transfer(int $oldMemberId, int $newMemberId): void
    {
        DB::connection('tenant')->transaction(function () use ($oldMemberId, $newMemberId) {

            
            $meterIssue = MeterIssue::on('tenant')->where('member_entry_id', $oldMemberId)->first();
            $oldMeterIssueId = $meterIssue->id;

            if ($meterIssue) {
                $newMeterIssueId = $this->transferMeterIssue($meterIssue, $newMemberId);
            }            
            $this->transferOpeningDeposit($oldMeterIssueId, $newMeterIssueId);

           
            $this->transferDepositTransactions($oldMeterIssueId, $newMeterIssueId);

           
            $this->transferOpeningShare($oldMemberId, $newMemberId);

         
            $this->transferShareTransactions($oldMemberId, $newMemberId);

           
            NameTransferEntry::on('tenant')->create([
                'previous_member_entry_id' => $oldMemberId,
                'new_member_entry_id' => $newMemberId,
            ]);

            
            MemberEntry::on('tenant')->where('id', $oldMemberId)->update(['is_active' => 0]);
        });
    }

     private function transferMeterIssue(MeterIssue $issue, int $newMemberId): int
    {
        $newIssue = $issue->replicate();
        $newIssue->member_entry_id = $newMemberId;
        $newIssue->save();

        $issue->transferred_to = $newIssue->id;
        $issue->is_active = 0;
        $issue->save();

        return $newIssue->id;
    }


    private function transferOpeningDeposit(int $oldId, int $newId)
    {
        $openingDeposit = MeterDepositTransaction::on('tenant')
            ->where('meter_issue_id', $oldId)
            ->first();

        if ($openingDeposit) {
            $newEntry = $openingDeposit->replicate();
            $newEntry->meter_issue_id = $newId;
            $newEntry->save();
            $openingDeposit->delete();
        }
    }

    private function transferDepositTransactions(int $oldId, int $newId): void
    {
        $hasReturn = MeterDepositTransaction::on('tenant')
            ->where('meter_issue_id', $oldId)
            ->where('transaction_type', 2)
            ->exists();

        $records = MeterDepositTransaction::on('tenant')
            ->where('meter_issue_id', $oldId)
            ->where('transaction_type', 1)
            ->get();

        foreach ($records as $tx) {
            // Only transfer type=0 if no type=1 exists
            if (!$hasReturn) {
                $newTx = $tx->replicate();
                $newTx->meter_issue_id = $newId;
                $newTx->save();
                $this->movePayments($tx->id, $newTx->id);
                $tx->delete();
            }
        }
    }

    private function transferOpeningShare(int $oldId, int $newId): void
    {
        $openingShare = ShareTransaction::on('tenant')
            ->where('member_entry_id', $oldId)
            ->where('transaction_type', 3)
            ->first();

        if ($openingShare) {
            $newEntry = $openingShare->replicate();
            $newEntry->member_entry_id = $newId;
            $newEntry->save();
            $openingShare->delete();
        }
    }

    private function transferShareTransactions(int $oldId, int $newId): void
    {
        $hasReturn = ShareTransaction::on('tenant')
            ->where('member_entry_id', $oldId)
            ->where('transaction_type', 2)
            ->exists();

        $records = ShareTransaction::on('tenant')
            ->where('member_entry_id', $oldId)
            ->where('transaction_type', 1)
            ->get();

        foreach ($records as $stx) {
            if (!$hasReturn) {
                $newTx = $stx->replicate();
                $newTx->member_entry_id = $newId;
                $newTx->save();
                $this->movePayments($stx->id, $newTx->id);
                $stx->delete();
            }
        }
    }

    private function movePayments(int $oldTxId, int $newTxId): void
    {
        $payments = Payment::on('tenant')->where('reference_id', $oldTxId)->get();

        foreach ($payments as $payment) {
            $newPayment = $payment->replicate();
            $newPayment->reference_id = $newTxId;
            $newPayment->save();
            $payment->delete();
        }
    }
}

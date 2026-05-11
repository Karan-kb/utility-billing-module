<?php

namespace App\Services;

use App\Models\VoucherSummary;
use App\Services\VoucherPreview\AdvancePaymentPreviewBuilder;
use App\Services\VoucherPreview\BankVoucherPreviewBuilder;
use App\Services\VoucherPreview\ExpenseAndReceivableTrackerPreviewBuilder;
use App\Services\VoucherPreview\JournalVoucherPreviewBuilder;
use App\Services\VoucherPreview\MahasulPreviewBuilder;
use App\Services\VoucherPreview\MeterDepositPreviewBuilder;
use App\Services\VoucherPreview\MeterInsurancePreviewBuilder;
use App\Services\VoucherPreview\NonMemberPaymentPreviewBuilder;
use App\Services\VoucherPreview\OtherIncomeReceiptPreviewBuilder;
use App\Services\VoucherPreview\SharePreviewBuilder;

class VoucherPreviewService
{
    public function getPreviewFromVoucher(
        int $voucherSummaryId,
        MemberInfoFromMeterIssueService $memberInfoService,
        PaymentService $paymentService,
         DateConverterService $dateService
    ): array {

        $voucher = VoucherSummary::findOrFail($voucherSummaryId);

        return match ($voucher->reference_type) {

                  1, 2, 3 => app(MeterDepositPreviewBuilder::class)->build(
        $voucher,
        $memberInfoService,
        $paymentService,
        $dateService
    ),

    4 => app(AdvancePaymentPreviewBuilder::class)->build(
        $voucher,
        $memberInfoService,
        $paymentService,
        $dateService
    ),

    5 => app(MeterInsurancePreviewBuilder::class)->build(
        $voucher,
        $memberInfoService,
        $paymentService,
        $dateService
    ),
    6 => app(OtherIncomeReceiptPreviewBuilder::class)->build(
    $voucher,
    $memberInfoService,
    $paymentService,
    $dateService
),

    7, 8 => app(SharePreviewBuilder::class)->build(
        $voucher,
        $memberInfoService,
        $paymentService,
        $dateService
    ),

    9 => app(NonMemberPaymentPreviewBuilder::class)->build(
        $voucher,
        $paymentService,
        $dateService
    ),

    11 => app(MahasulPreviewBuilder::class)->build(
        $voucher,
        $memberInfoService,
        $paymentService,
        $dateService
    ),
    12 => app(ExpenseAndReceivableTrackerPreviewBuilder::class)->build(
    $voucher,
    $paymentService,
    $dateService
),
        13 => app(BankVoucherPreviewBuilder::class)->build(
            $voucher,
             $paymentService,
            $dateService
        ),
        14 => app(JournalVoucherPreviewBuilder::class)->build(
            $voucher,
            $dateService
        ),

            default => throw new \Exception(
                'Preview not implemented for this voucher type'
            ),
        };
    }
}

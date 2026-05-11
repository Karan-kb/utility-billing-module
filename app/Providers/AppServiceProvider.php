<?php

namespace App\Providers;

use App\Models\AdvancePayment;
use App\Models\BankVoucher;
use App\Models\BlacklistPeriod;
use App\Models\ChangeMeter;
use App\Models\DiscountAndFine;
use App\Models\ExpenseAndReceivableTracker;
use App\Models\Fine;
use App\Models\JournalVoucher;
use App\Models\MahasulReceiptEntry;
use App\Models\MeterDepositTransaction;
use App\Models\MeterInsurance;
use App\Models\MeterIssue;
use App\Models\NameTransferEntry;
use App\Models\NEAPaymentEntry;
use App\Models\NonMemberPayment;
use App\Models\OpeningBalanceEntry;
use App\Models\OtherIncomeReceipt;
use App\Models\RateAndCapacity;
use App\Models\ShareTransaction;
use App\Models\UpgradeMeterCapacity;
use App\Observers\activity_logs\AdvancePaymentObserver;
use App\Observers\activity_logs\BankVoucherObserver;
use App\Observers\activity_logs\BlacklistPeriodObserver;
use App\Observers\activity_logs\ChangeMeterObserver;
use App\Observers\activity_logs\DiscountAndFineObserver;
use App\Observers\activity_logs\ExpenseAndReceivableTrackerObserver;
use App\Observers\activity_logs\FineObserver;
use App\Observers\activity_logs\JournalVoucherObserver;
use App\Observers\activity_logs\MahasulReceiptEntryObserver;
use App\Observers\activity_logs\MeterDepositTransactionObserver;
use App\Observers\activity_logs\MeterInsuranceObserver;
use App\Observers\activity_logs\MeterIssueObserver;
use App\Observers\activity_logs\NameTransferEntryObserver;
use App\Observers\activity_logs\OtherIncomeReceiptObserver;
use App\Observers\activity_logs\NEAPaymentEntryObserver;
use App\Observers\activity_logs\NonMemberPaymentObserver;
use App\Observers\activity_logs\OpeningBalanceEntryObserver;
use App\Observers\activity_logs\RateAndCapacityObserver;
use App\Observers\activity_logs\ShareTransactionObserver;
use App\Observers\activity_logs\UpgradeMeterCapacityObserver;
use App\Repositories\BankVoucherRepository;
use App\Repositories\ExpenseAndReceivableTrackerRepository;
use App\Repositories\Interfaces\BankVoucherRepositoryInterface;
use App\Repositories\Interfaces\ExpenseAndReceivableTrackerRepositoryInterface;
use App\Repositories\Interfaces\JournalVoucherRepositoryInterface;
use App\Repositories\Interfaces\OpeningBalanceRepositoryInterface;
use App\Repositories\Interfaces\OpeningMahasulFineSetupRepositoryInterface;
use App\Repositories\JournalVoucherRepository;
use App\Repositories\OpeningBalanceRepository;
use App\Repositories\OpeningMahasulFineSetupRepository;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Schema\Blueprint;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
                $this->app->bind(ExpenseAndReceivableTrackerRepositoryInterface::class, ExpenseAndReceivableTrackerRepository::class);
                $this->app->bind(BankVoucherRepositoryInterface::class, BankVoucherRepository::class);
                $this->app->bind(JournalVoucherRepositoryInterface::class, JournalVoucherRepository::class);
                $this->app->bind(OpeningBalanceRepositoryInterface::class, OpeningBalanceRepository::class);
                $this->app->bind(OpeningMahasulFineSetupRepositoryInterface::class, OpeningMahasulFineSetupRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        AdvancePayment::observe(AdvancePaymentObserver::class);
        MeterInsurance::observe(MeterInsuranceObserver::class);
        OtherIncomeReceipt::observe(OtherIncomeReceiptObserver::class);
        NEAPaymentEntry::observe(NEAPaymentEntryObserver::class);
        BlacklistPeriod::observe(BlacklistPeriodObserver::class);
        ChangeMeter::observe(ChangeMeterObserver::class);
        DiscountAndFine::observe(DiscountAndFineObserver::class);
        MahasulReceiptEntry::observe(MahasulReceiptEntryObserver::class);
        MeterDepositTransaction::observe(MeterDepositTransactionObserver::class);
        MeterIssue::observe(MeterIssueObserver::class);
        NameTransferEntry::observe(NameTransferEntryObserver::class);
        NonMemberPayment::observe(NonMemberPaymentObserver::class);
        RateAndCapacity::observe(RateAndCapacityObserver::class);
        ShareTransaction::observe(ShareTransactionObserver::class);
        UpgradeMeterCapacity::observe(UpgradeMeterCapacityObserver::class);
        Fine::observe(FineObserver::class);
        ExpenseAndReceivableTracker::observe(ExpenseAndReceivableTrackerObserver::class);
        BankVoucher::observe(BankVoucherObserver::class);
        JournalVoucher::observe(JournalVoucherObserver::class);
        OpeningBalanceEntry::observe(OpeningBalanceEntryObserver::class);

     


        Blueprint::macro('auditFields', function () {
            $this->unsignedBigInteger('created_by')->nullable();
            $this->unsignedBigInteger('updated_by')->nullable();
            $this->unsignedBigInteger('deleted_by')->nullable();
        });
    }
}

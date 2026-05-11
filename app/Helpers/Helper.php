<?php

namespace App\Helpers;

use App\Models\MeasureUnit;
use App\Models\Product;
use App\Models\FiscalYear;
use App\Models\UpgradeMeterCapacity;
use App\Models\ProductList;
use App\Models\Purchase;
use Carbon\Carbon;
use App\Models\PurchaseProduct;
use App\Models\Sale;
use App\Models\SaleProduct;
use App\Models\SalesReturn;
use App\Models\SalesReturnProduct;
use Cache;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;

class Helper
{



    public static function castToDouble($value)
    {
        return is_numeric($value) ? (double) $value : null;
    }


    public static function bc_sum(array $amounts, int $scale = 4): string
    {
        $total = '0';
        foreach ($amounts as $amount) {
            $total = bcadd($total, (string) ($amount ?? 0), $scale);
        }

        return $total;
    }

    public static function getFiscalYearCode(): string
    {
        $adDate = Carbon::now()->format('Y-m-d');
        $bsDate = NepaliCalendar::adToBs($adDate);
        [$year, $month] = explode('-', $bsDate);

        $fiscalYear = ((int) $month >= 4) ? (int) $year : (int) $year - 1;
        return substr($fiscalYear, 2, 2) . substr($fiscalYear + 1, 2, 2);
    }

    public static function generateUpgradeVoucherNo(): string
    {
        $fiscalYearCode = self::getFiscalYearCode();
        $lastUpgrade = UpgradeMeterCapacity::withTrashed()
            ->where('voucher_no', 'like', "UG{$fiscalYearCode}%")
            ->orderBy('id', 'desc')
            ->first();

        $lastNumber = $lastUpgrade ? (int) substr($lastUpgrade->voucher_no, 8) : 0;
        return "UG{$fiscalYearCode}-" . str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);
    }

    public static function fiscalYears()
    {
        // Insert Fiscal Years
        $fiscalYears = [
            ['year_en' => '2082/2083', 'year_np' => '२०८२/२०८३', 'start_date' => '2025-07-17', 'end_date' => '2027-04-13'],
            ['year_en' => '2083/2084', 'year_np' => '२०८३/२०८४', 'start_date' => '2026-07-17', 'end_date' => '2028-04-12'],
            ['year_en' => '2084/2085', 'year_np' => '२०८४/२०८५', 'start_date' => '2027-07-17', 'end_date' => '2029-04-13'],
            ['year_en' => '2085/2086', 'year_np' => '२०८५/२०८६', 'start_date' => '2028-07-16', 'end_date' => '2030-04-13'],
            ['year_en' => '2086/2087', 'year_np' => '२०८६/२०८७', 'start_date' => '2029-07-16', 'end_date' => '2031-04-14'],
            ['year_en' => '2087/2088', 'year_np' => '२०८७/२०८८', 'start_date' => '2030-07-17', 'end_date' => '2032-04-13'],
            ['year_en' => '2088/2089', 'year_np' => '२०८८/२०८९', 'start_date' => '2031-07-17', 'end_date' => '2033-04-13'],
            ['year_en' => '2089/2090', 'year_np' => '२०८९/२०९०', 'start_date' => '2032-07-16', 'end_date' => '2034-04-13'],
            ['year_en' => '2090/2091', 'year_np' => '२०९०/२०९१', 'start_date' => '2033-07-16', 'end_date' => '2035-04-14'],
            ['year_en' => '2091/2092', 'year_np' => '२०९१/२०९२', 'start_date' => '2034-07-17', 'end_date' => '2036-04-13'],
        ];

        // Insert or update fiscal years
        foreach ($fiscalYears as $fy) {
            FiscalYear::updateOrCreate(
                ['year_en' => $fy['year_en']],
                [
                    'year_np' => $fy['year_np'],
                    'start_date' => $fy['start_date'],
                    'end_date' => $fy['end_date'],
                    'status' => 0,
                ]
            );
        }

        // Auto-detect and activate current fiscal year
        $today = now()->toDateString();

        $currentFiscal = FiscalYear::where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->first();

        // Set all inactive first
        FiscalYear::query()->update(['status' => 0]);

        if ($currentFiscal) {
            $currentFiscal->update(['status' => 1]);
        }


    }

    public static function getActiveFiscalYearId()
    {
        $id = FiscalYear::where('status', 1)->value('id');
        return $id;
    }
    public static function moduleTypeComment(): string
{
    return collect(config('module_types'))
        ->map(fn($name, $id) => "$id=$name")
        ->implode(', ');
}
public static function paymentTypeComment(): string
{
     return collect(config('payment_types'))
        ->map(fn($name, $id) => "$id=$name")
        ->implode(', ');
}
public static function voucherTypeComment(): string
{
     return collect(config('voucher_types'))
        ->map(fn($name, $id) => "$id=$name")
        ->implode(', ');
        }
}
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Helpers\NepaliCalendar;

class DateConversionController extends Controller
{
    public function adToBs(Request $request)
    {
        try {
            $adDate = Carbon::now()->format('Y-m-d');
            $bsDate = NepaliCalendar::adToBs($adDate);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'bs_date' => $bsDate,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid date format or error in conversion: ' . $e->getMessage()
            ], 400);
        }
    }

    public function bsToAd(Request $request)
    {
        try {
            $bsDate = $request->input('date', '2082-02-04');
            $adDate = NepaliCalendar::bsToAd($bsDate);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'ad_date' => $adDate,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid BS date format or error in conversion: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get the current date in BS format.
     *
     * @return string
     * @throws \Exception
     */
    public function getCurrentBsDate()
    {
        try {
            $adDate = Carbon::now()->format('Y-m-d');
            $bsDate = NepaliCalendar::adToBs($adDate);
            return $bsDate;
        } catch (\Exception $e) {

            throw new \Exception('Failed to convert current date to BS: ' . $e->getMessage());
        }
    }
}

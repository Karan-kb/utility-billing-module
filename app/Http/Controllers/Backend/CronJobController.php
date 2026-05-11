<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Artisan;


class CronJobController extends Controller
{
    public function runFines(Request $request)
    {
        $date = $request->input('date');

        if (!$date) {
            return response()->json([
                'success' => false,
                'message' => 'Date parameter is required.'
            ], 422);
        }



        Artisan::call('fines:apply', [
            '--date' => $date,
        ]);


        $output = Artisan::output();

        return response()->json([
            'success' => true,
            'message' => 'Fines command executed !',
            'date_used' => $date,
            'artisan_output' => $output
        ]);
    }
}

<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Models\PasswordOtp;
use Illuminate\Support\Facades\Mail;

class ForgotPasswordController extends Controller
{
    // Step 1: Send OTP
    public function sendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();

        $company = Company::find($user->company_id);
        if (!$company || !$company->email_address) {
            return response()->json(['message' => 'Company email not found for this user'], 422);
        }

        $otp = mt_rand(100000, 999999);
        $expiry = now()->addMinutes(10);

        PasswordOtp::updateOrCreate(
            ['user_id' => $user->id],
            ['otp' => $otp, 'expires_at' => $expiry]
        );

        Mail::raw("Your OTP for password reset is: {$otp}. It will expire in 10 minutes.", function ($message) use ($company) {
            $message->to($company->email_address)
                ->subject('Password Reset OTP');
        });

        return response()->json([
            'message' => 'OTP sent to the company email',
            'otp_expiry_minutes' => 10
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|digits:6',
        ]);

        $user = User::where('email', $request->email)->first();

        $otpEntry = PasswordOtp::where('user_id', $user->id)
            ->where('otp', $request->otp)
            ->where('expires_at', '>', now())
            ->first();

        if (!$otpEntry) {
            return response()->json(['message' => 'Invalid or expired OTP'], 422);
        }

        return response()->json([
            'message' => 'OTP verified. You can now reset your password.'
        ]);
    }

    // Step 3: Reset password
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|digits:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::where('email', $request->email)->first();

        $otpEntry = PasswordOtp::where('user_id', $user->id)
            ->where('otp', $request->otp)
            ->where('expires_at', '>', now())
            ->first();

        if (!$otpEntry) {
            return response()->json(['message' => 'Invalid or expired OTP'], 422);
        }

        // Reset password
        $user->password = Hash::make($request->password);
        $user->save();

        // Delete OTP after use
        $otpEntry->delete();

        return response()->json([
            'message' => 'Password reset successfully'
        ]);
    }
}

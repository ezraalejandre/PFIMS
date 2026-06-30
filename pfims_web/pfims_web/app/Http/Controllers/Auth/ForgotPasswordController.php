<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PasswordOtp;
use App\Models\User;
use App\Notifications\ForgotPasswordOtpNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ForgotPasswordController extends Controller
{
    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid email format',
            ], 422);
        }

        $email = $request->input('email');
        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'No account found with that email address',
            ], 404);
        }

        $otp = (string) random_int(100000, 999999);

        PasswordOtp::updateOrCreate(
            ['email' => $email],
            [
                'otp' => $otp,
                'verified' => false,
                'expires_at' => now()->addMinutes(10),
            ]
        );

        $user->notify(new ForgotPasswordOtpNotification($otp));

        return response()->json([
            'message' => 'Verification code sent',
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|digits:6',
        ]);

        $record = PasswordOtp::where('email', $request->email)
            ->where('otp', $request->otp)
            ->first();

        if (!$record) {
            return response()->json(['message' => 'Invalid code'], 422);
        }

        if ($record->expires_at->isPast()) {
            return response()->json(['message' => 'Code has expired'], 422);
        }

        $record->update(['verified' => true]);

        return response()->json(['message' => 'Code verified']);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|digits:6',
            'password' => 'required|min:8|confirmed',
        ]);

        $record = PasswordOtp::where('email', $request->email)
            ->where('otp', $request->otp)
            ->where('verified', true)
            ->first();

        if (!$record || $record->expires_at->isPast()) {
            return response()->json([
                'message' => 'Code not verified or has expired',
            ], 422);
        }

        $user = User::where('email', $request->email)->firstOrFail();

        $user->forceFill([
            'password' => Hash::make($request->password),
        ])->save();

        $record->delete();

        return response()->json(['message' => 'Password has been reset']);
    }
}
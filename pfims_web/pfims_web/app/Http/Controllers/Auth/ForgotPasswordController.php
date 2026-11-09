<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PasswordOtp;
use App\Models\User;
use App\Notifications\ForgotPasswordOtpNotification;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

// Group the forgot password actions.
class ForgotPasswordController extends Controller
{
    // Send otp.
    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        // Return early if the submitted fields fail validation.
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid email format',
            ], 422);
        }

        $email = $request->input('email');
        $user = User::where('email', $email)->first();

        // Return early if the user is missing or false.
        if (!$user) {
            return response()->json([
                'message' => 'If that email exists in our system, a reset code has been sent.',
            ]);
        }

        $otp = (string) random_int(100000, 999999);

        PasswordOtp::updateOrCreate(
            ['email' => $email],
            [
                'otp' => Hash::make($otp),
                'verified' => false,
                'expires_at' => now()->addMinutes(10),
            ]
        );

        $user->notify(new ForgotPasswordOtpNotification($otp));

        return response()->json([
            'message' => 'If that email exists in our system, a reset code has been sent.',
        ]);
    }

    // Get the verify otp.
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|digits:6',
        ]);

        $record = PasswordOtp::where('email', $request->email)->first();

        // Return early if the record and otp checks match.
        if (!$record || !Hash::check($request->otp, $record->otp)) {
            return response()->json(['message' => 'Invalid code'], 422);
        }

        // Return early if the is past check matches.
        if ($record->expires_at->isPast()) {
            return response()->json(['message' => 'Code has expired'], 422);
        }

        $record->update(['verified' => true]);

        return response()->json(['message' => 'Code verified']);
    }

    // Get the reset.
    public function reset(Request $request, AuditLogService $audit)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|digits:6',
            'password' => 'required|string|min:8|max:72|confirmed',
        ]);

        $record = PasswordOtp::where('email', $request->email)
            ->where('verified', true)
            ->first();

        // Return early if the record and otp checks match.
        if (!$record || !Hash::check($request->otp, $record->otp) || $record->expires_at->isPast()) {
            return response()->json([
                'message' => 'Code not verified or has expired',
            ], 422);
        }

        $user = User::where('email', $request->email)->firstOrFail();

        $user->forceFill([
            'password' => Hash::make($request->password),
        ])->save();

        $audit->recordPasswordReset($user);

        $record->delete();

        return response()->json(['message' => 'Password has been reset']);
    }
}

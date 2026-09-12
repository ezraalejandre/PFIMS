<?php
// app/Http/Controllers/Auth/ForgotPasswordControllerWeb.php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\OtpMail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class ForgotPasswordControllerWeb extends Controller
{
    /**
     * STEP 1: Person submits their email.
     * We generate a 6-digit OTP, store its hash, and email it.
     */
    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        // Don't reveal whether the email exists in the system.
        if (!$user) {
            return response()->json([
                'success' => true,
                'message' => 'If that email exists in our system, a reset code has been sent.',
            ]);
        }

        // Simple throttle: 1 request per 60 seconds per email.
        $recent = DB::table('password_reset_otps')->where('email', $request->email)->first();
        if ($recent && Carbon::parse($recent->updated_at)->diffInSeconds(now()) < 60) {
            return response()->json([
                'success' => false,
                'message' => 'Please wait a bit before requesting another code.',
            ], 429);
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('password_reset_otps')->updateOrInsert(
            ['email' => $request->email],
            [
                'otp' => Hash::make($otp),
                'expires_at' => now()->addMinutes(10),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        Mail::to($request->email)->send(new OtpMail($otp, $user->name ?? ''));

        return response()->json([
            'success' => true,
            'message' => 'A 6-digit reset code has been sent to your email.',
        ]);
    }

    /**
     * STEP 2: Person submits the OTP they received.
     * If valid, we issue a short-lived reset token used to authorize step 3,
     * so the new-password request itself never has to re-send the raw OTP.
     */
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'otp' => ['required', 'digits:6'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $record = DB::table('password_reset_otps')->where('email', $request->email)->first();

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'No reset request found. Please request a new code.',
            ], 404);
        }

        if (Carbon::parse($record->expires_at)->isPast()) {
            DB::table('password_reset_otps')->where('email', $request->email)->delete();
            return response()->json([
                'success' => false,
                'message' => 'This code has expired. Please request a new one.',
            ], 410);
        }

        if (!Hash::check($request->otp, $record->otp)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid code. Please try again.',
            ], 422);
        }

        // OTP confirmed — swap it out for a one-time reset token (10 more minutes to set a new password).
        $resetToken = bin2hex(random_bytes(32));

        DB::table('password_reset_otps')
            ->where('email', $request->email)
            ->update([
                'otp' => Hash::make($resetToken),
                'expires_at' => now()->addMinutes(10),
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Code verified successfully.',
            'reset_token' => $resetToken,
        ]);
    }

    /**
     * STEP 3: Person submits the new password along with the reset token from step 2.
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'reset_token' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $record = DB::table('password_reset_otps')->where('email', $request->email)->first();

        if (!$record || Carbon::parse($record->expires_at)->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Your reset session has expired. Please start again.',
            ], 410);
        }

        if (!Hash::check($request->reset_token, $record->otp)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid reset session. Please start again.',
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        DB::table('password_reset_otps')->where('email', $request->email)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully. You can now sign in.',
        ]);
    }
}
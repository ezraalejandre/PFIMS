<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
// Group the account session actions.
class AccountSessionService {
    // Get the claim.
    public function claim(object $user, ?string $ownedToken=null): string {
        // Run these database steps together. Undo changes if a step fails.
        return DB::transaction(function() use($user,$ownedToken) {
            DB::table('users')->where('id',$user->id)->lockForUpdate()->firstOrFail();
            $active=DB::table('account_sessions')->where('user_id',$user->id)->first();
            $fingerprint=hash('sha256',$user->password);
            // Run this step if the active and expires at checks match.
            if($active && now()->lessThan($active->expires_at) && hash_equals($active->credential_hash,$fingerprint)
                && (!$ownedToken || !hash_equals($active->token_hash,hash('sha256',$ownedToken)))) {
                abort(409,'This account is already signed in on another browser or device. Sign out there before signing in here.');
            }
            $token=bin2hex(random_bytes(32));
            DB::table('account_sessions')->updateOrInsert(['user_id'=>$user->id],['token_hash'=>hash('sha256',$token),'credential_hash'=>$fingerprint,'closing_at'=>null,'page_nonce'=>null,'expires_at'=>now()->addSeconds((int)config('session.account_lease_seconds',60))]);
            return $token;
        });
    }
    // Get the valid.
    public function valid(object $user, ?string $token): bool {
        // Return early if the token is missing or false.
        if(!$token)return false;
        $active=DB::table('account_sessions')->where('user_id',$user->id)->first();
        // Return early if the active and expires at checks match.
        if(!$active || now()->greaterThanOrEqualTo($active->expires_at) || !hash_equals($active->token_hash,hash('sha256',$token)) || !hash_equals($active->credential_hash,hash('sha256',$user->password)))return false;
        // Run this step if the closing at is missing or false.
        if (!$active->closing_at) DB::table('account_sessions')->where('user_id',$user->id)->where('token_hash',hash('sha256',$token))->update(['expires_at'=>now()->addSeconds((int)config('session.account_lease_seconds',60))]);
        return true;
    }
    // Get the inactivity timeout seconds.
    public function inactivityTimeoutSeconds(object $user, ?string $token): ?int {
        // Return early if the token is missing or false.
        if (! $token) return null;
        $active = DB::table('account_sessions')->where('user_id', $user->id)->first();
        // Return early if the active and closing at checks match.
        if (! $active || $active->closing_at || now()->lessThan($active->expires_at)
            || ! hash_equals($active->token_hash, hash('sha256', $token))
            || ! hash_equals($active->credential_hash, hash('sha256', $user->password))) return null;

        return (int) config('session.account_lease_seconds', 60);
    }
    // Get the release.
    public function release(?object $user, ?string $token): void {
        // Run this step if the user and token checks match.
        if($user && $token)DB::table('account_sessions')->where('user_id',$user->id)->where('token_hash',hash('sha256',$token))->delete();
    }
}

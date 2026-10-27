<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
class AccountSessionService {
    public function claim(object $user, ?string $ownedToken=null): string {
        return DB::transaction(function() use($user,$ownedToken) {
            DB::table('users')->where('id',$user->id)->lockForUpdate()->firstOrFail();
            $active=DB::table('account_sessions')->where('user_id',$user->id)->first();
            $fingerprint=hash('sha256',$user->password);
            if($active && now()->lessThan($active->expires_at) && hash_equals($active->credential_hash,$fingerprint)
                && (!$ownedToken || !hash_equals($active->token_hash,hash('sha256',$ownedToken)))) {
                abort(409,'This account is already signed in on another browser or device. Sign out there before signing in here.');
            }
            $token=bin2hex(random_bytes(32));
            DB::table('account_sessions')->updateOrInsert(['user_id'=>$user->id],['token_hash'=>hash('sha256',$token),'credential_hash'=>$fingerprint,'closing_at'=>null,'page_nonce'=>null,'expires_at'=>now()->addSeconds((int)config('session.account_lease_seconds',60))]);
            return $token;
        });
    }
    public function valid(object $user, ?string $token): bool {
        if(!$token)return false;
        $active=DB::table('account_sessions')->where('user_id',$user->id)->first();
        if(!$active || now()->greaterThanOrEqualTo($active->expires_at) || !hash_equals($active->token_hash,hash('sha256',$token)) || !hash_equals($active->credential_hash,hash('sha256',$user->password)))return false;
        if (!$active->closing_at) DB::table('account_sessions')->where('user_id',$user->id)->where('token_hash',hash('sha256',$token))->update(['expires_at'=>now()->addSeconds((int)config('session.account_lease_seconds',60))]);
        return true;
    }
    public function release(?object $user, ?string $token): void {
        if($user && $token)DB::table('account_sessions')->where('user_id',$user->id)->where('token_hash',hash('sha256',$token))->delete();
    }
}

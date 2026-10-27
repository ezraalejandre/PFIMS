<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected bool $simulateInternalNavigation = true;
    private array $fixtureAccountTokens = [];
    public function actingAs(\Illuminate\Contracts\Auth\Authenticatable $user, $guard = null)
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('account_sessions')) (require database_path('migrations/2026_10_18_000002_create_account_sessions_table.php'))->up();
        $token = app(\App\Services\AccountSessionService::class)->claim($user, $this->fixtureAccountTokens[$user->getAuthIdentifier()] ?? null);
        $this->fixtureAccountTokens[$user->getAuthIdentifier()] = $token;
        $this->withSession(['account_session_token'=>$token]);
        return parent::actingAs($user, $guard);
    }
    // Legacy tests build narrowly scoped schemas; provision shared recovery storage before DELETE requests.
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('users') && !\Illuminate\Support\Facades\Schema::hasTable('account_sessions')) (require database_path('migrations/2026_10_18_000002_create_account_sessions_table.php'))->up();
        if (strtoupper($method) === 'DELETE' && !\Illuminate\Support\Facades\Schema::hasTable('trash_entries')) {
            (require database_path('migrations/2026_10_18_000001_create_trash_entries_table.php'))->up();
        }
        if ($this->simulateInternalNavigation && strtoupper($method)==='GET' && auth()->check()) {
            $request=\Illuminate\Http\Request::create($uri);$request->setLaravelSession($this->app['session.store']);
            app(\App\Services\NavigationGrantService::class)->allow($request,$uri);
        }
        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }
}

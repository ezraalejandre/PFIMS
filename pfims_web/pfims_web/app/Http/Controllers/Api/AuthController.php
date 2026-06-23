<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $username = $request->username;

        $user = DB::table('user_tbl')
            ->where('username', $username)
            ->first();


        if (!$user) {
            return response()->json([
                "success" => false,
                "message" => "User not found"
            ], 401);
        }


        return response()->json([
            "success" => true,
            "user" => [
                "user_id" => $user->user_id,
                "username" => $user->username,
                "role" => $user->role,
                "first_name" => $user->first_name,
                "last_name" => $user->last_name,
            ]
        ]);
    }
}
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class AuthController extends Controller
{

    public function login(Request $request)
    {

        $user = User::where('username', $request->username)
            ->where('password', $request->password)
            ->first();


        if (!$user) {

            return response()->json([
                "success" => false,
                "message" => "Invalid username or password"
            ], 401);

        }


        return response()->json([
            "success" => true,
            "role" => $user->role,
            "username" => $user->username
        ]);

    }



public function profile(Request $request)
{

    $user = User::where(
        'username',
        $request->username
    )->first();


    if(!$user){

        return response()->json([
            "success"=>false
        ],404);

    }


    return response()->json([
        "success"=>true,
        "user"=>$user
    ]);

}



public function changePassword(Request $request)
{

    $user = User::where(
        'username',
        $request->username
    )->first();


    if(!$user){

        return response()->json([
            "success"=>false,
            "message"=>"User not found"
        ],404);

    }



    $user->password =
        $request->new_password;


    $user->save();



    return response()->json([
        "success"=>true,
        "message"=>"Password updated",
        "password"=>$user->password
    ]);

}

}







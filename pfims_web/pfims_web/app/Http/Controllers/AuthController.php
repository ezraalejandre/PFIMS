<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
class AuthController extends Controller
{

public function login(Request $request)
{

    $user = User::where(
        'email',
        $request->email
    )->first();


    if(!$user){

        return response()->json([
            "success"=>false,
            "message"=>"Invalid email or password"
        ],401);

    }


   
    if (!Hash::check($request->password, $user->password)) {
        return response()->json([
            "success" => false,
            "message" => "Invalid email or password"
        ], 401);
    }


      return response()->json([
        "success" => true,
        "user" => [
            "id" => $user->id,
            "name" => $user->name,
            "email" => $user->email,
            "role" => $user->role,
        ]
    ]);

}



// public function profile(Request $request)
// {

//     $user = User::where(
//         'username',
//         $request->username
//     )->first();


//     if(!$user){

//         return response()->json([
//             "success"=>false
//         ],404);

//     }


//     return response()->json([
//         "success"=>true,
//         "user"=>$user
//     ]);

// }


public function profile(Request $request)
{


$user = User::where(
'email',
$request->email
)->first();



return response()->json([

"user"=>$user

]);


}



// public function changePassword(Request $request)
// {

//     $user = User::where(
//         'username',
//         $request->username
//     )->first();


//     if(!$user){

//         return response()->json([
//             "success"=>false,
//             "message"=>"User not found"
//         ],404);

//     }



//     $user->password =
//         $request->new_password;


//     $user->save();



//     return response()->json([
//         "success"=>true,
//         "message"=>"Password updated",
//         "password"=>$user->password
//     ]);

// }


public function changePassword(Request $request)
{


$user = User::where(
'email',
$request->email
)->first();



$user->password =
$request->new_password;


$user->save();



return response()->json([

"success"=>true,

"user"=>$user

]);


}
}







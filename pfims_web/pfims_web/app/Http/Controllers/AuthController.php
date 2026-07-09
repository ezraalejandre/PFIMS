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
    $user = User::where('email', $request->email)->first();

    if (!$user) {
        return response()->json([
            "success" => false,
            "message" => "User not found",
        ], 404);
    }

    return response()->json([
        "success" => true,
        "user" => [
            "id" => $user->id,
            "name" => $user->name,
            "email" => $user->email,
            "phone" => $user->phone,
            "location" => $user->location,
            "role" => $user->role,
            "profile_photo" => $user->profile_photo
                ? asset('storage/' . $user->profile_photo)
                : null,
        ],
    ]);
}

public function uploadProfilePhoto(Request $request)
{
    $request->validate([
        'email' => ['required', 'email'],
        'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'], // 5MB
    ]);

    $user = User::where('email', $request->email)->first();

    if (!$user) {
        return response()->json([
            "success" => false,
            "message" => "User not found",
        ], 404);
    }

    // Delete the old photo, if any, before saving the new one.
    if ($user->profile_photo) {
        \Illuminate\Support\Facades\Storage::disk('public')->delete($user->profile_photo);
    }

    $path = $request->file('photo')->store('profile_photos', 'public');

    $user->profile_photo = $path;
    $user->save();

    return response()->json([
        "success" => true,
        "profile_photo" => asset('storage/' . $path),
    ]);
}


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







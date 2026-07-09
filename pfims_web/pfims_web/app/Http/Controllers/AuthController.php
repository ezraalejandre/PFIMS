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
            "profile_photo" => $this->photoDataUri($user),
        ],
    ]);
}

public function uploadProfilePhoto(Request $request)
{
    $request->validate([
        'email' => ['required', 'email'],
        'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
    ]);

    $user = User::where('email', $request->email)->first();

    if (!$user) {
        return response()->json([
            "success" => false,
            "message" => "User not found",
        ], 404);
    }

    $file = $request->file('photo');

    // Store the image directly in the database as base64 text, rather
    // than on local disk. This removes the CORS problem entirely (the
    // browser never fetches a separate static file — the image comes
    // back embedded in this same JSON response) and means the photo
    // can never go "missing" because a file got deleted or the storage
    // symlink wasn't set up.
    $user->profile_photo_data = base64_encode(file_get_contents($file->getRealPath()));
    $user->profile_photo_mime = $file->getMimeType();
    $user->save();

    return response()->json([
        "success" => true,
        "profile_photo" => $this->photoDataUri($user),
    ]);
}

// Builds a "data:image/jpeg;base64,...." URI from the stored base64
// image data + MIME type, or null if the user has no photo saved.
// Flutter decodes this directly into bytes for MemoryImage.
private function photoDataUri(User $user): ?string
{
    if (!$user->profile_photo_data || !$user->profile_photo_mime) {
        return null;
    }

    return "data:{$user->profile_photo_mime};base64,{$user->profile_photo_data}";
}


public function changePassword(Request $request)
{
    $request->validate([
        'email' => ['required', 'email'],
        'current_password' => ['required', 'string'],
        'new_password' => ['required', 'string', 'min:8'],
    ]);

    $user = User::where('email', $request->email)->first();

    if (!$user) {
        return response()->json([
            "success" => false,
            "message" => "User not found",
        ], 404);
    }

    if (!Hash::check($request->current_password, $user->password)) {
        return response()->json([
            "success" => false,
            "message" => "Current password is incorrect",
        ], 401);
    }

    // Hash the new password before storing it — never save plaintext.
    $user->password = Hash::make($request->new_password);
    $user->save();

    return response()->json([
        "success" => true,
        "message" => "Password updated successfully",
    ]);
}
}
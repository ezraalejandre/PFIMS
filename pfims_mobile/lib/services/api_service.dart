import 'dart:convert';
import 'package:http/http.dart' as http;

class ApiService {

//   static Future<Map<String,dynamic>> login(
//       String username,
//       String password
//   ) async {


//     final response = await http.post(
//       Uri.parse(
//         "http://127.0.0.1:8000/api/login"
//       ),
//       body: {
//         "username": username,
//         "password": password
//       },
//     );


//     print(response.body);


//     if(response.statusCode == 200){

//       return jsonDecode(response.body);

//     }
//     else {

//       throw Exception(
//         "Login failed ${response.body}"
//       );

//     }

//   }


// static Future<Map<String,dynamic>> getProfile(
// String username
// ) async {


// final response =
// await http.post(
//  Uri.parse(
//   "http://127.0.0.1:8000/api/profile"
//  ),

//  body:{
//   "username":username
//  }
// );


// return jsonDecode(response.body);

// }

static Future<Map<String,dynamic>> login(
String email,
String password
) async {


final response =
await http.post(

Uri.parse(
"http://127.0.0.1:8000/api/login"
),


body:{

"email":email,

"password":password

}

);


if(response.statusCode != 200){

throw Exception(
"Invalid login"
);

}


return jsonDecode(
response.body
);


}

// static Future<Map<String,dynamic>> changePassword(
// String username,
// String newPassword
// ) async {


// final response =
// await http.post(
//  Uri.parse(
//  "http://127.0.0.1:8000/api/change-password"
//  ),

//  body:{
//   "username":username,
//   "new_password":newPassword
//  }
// );


// return jsonDecode(response.body);

// }


static Future<Map<String,dynamic>> changePassword(
String email,
String password
) async {


final response =
await http.post(

Uri.parse(
"http://127.0.0.1:8000/api/change-password"
),

body:{

"email":email,

"new_password":password

}

);


return jsonDecode(
response.body
);

}


static Future<Map<String,dynamic>> getProfile(
String email
) async {


final response =
await http.post(

Uri.parse(
"http://127.0.0.1:8000/api/profile"
),

body:{

"email":email

}

);


return jsonDecode(
response.body
);


}

}
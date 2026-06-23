import 'dart:convert';
import 'package:http/http.dart' as http;


class ApiService {

  static const String baseUrl =
      "http://10.0.2.2:8000/api";


  static Future login(String username) async {


    final response = await http.post(
      Uri.parse("$baseUrl/login"),
      body: {
        "username": username,
      },
    );


    if(response.statusCode == 200){

      return jsonDecode(response.body);

    } else {

      throw Exception("Invalid login");

    }

  }
}






// import 'dart:convert';
// import 'package:http/http.dart' as http;

// class ApiService {

//   static const String baseUrl =
//       "http://127.0.0.1:8000/api";
//       // android emulator: 10.0.2.2:8000
//       // web: 127.0.0.1:8000
//       // phone: PC IP:8000


//   static Future<String> testConnection() async {

//     final response = await http.get(
//       Uri.parse("$baseUrl/test"),
//     );


//     if(response.statusCode == 200){

//       final data = jsonDecode(response.body);

//       return data['message'];

//     }else{

//       throw Exception("Failed to connect");

//     }
//   }
// }
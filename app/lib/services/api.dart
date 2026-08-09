import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

class Api {
  static const String origin = 'https://bbb.ezzy500.vip';
  static const String base = '$origin/api';
  static String? token;

  static Future<void> init() async {
    token = (await SharedPreferences.getInstance()).getString('token');
  }

  static Map<String,String> get headers => {
    'Accept':'application/json',
    'Content-Type':'application/json',
    if (token != null) 'Authorization':'Bearer $token',
  };

  static Future<Map<String,dynamic>> request(String path,{String method='GET',Map<String,dynamic>? body}) async {
    final uri = Uri.parse('$base$path');
    http.Response response;
    final payload = body == null ? null : jsonEncode(body);
    switch(method){
      case 'POST': response = await http.post(uri,headers:headers,body:payload); break;
      case 'PUT': response = await http.put(uri,headers:headers,body:payload); break;
      case 'DELETE': response = await http.delete(uri,headers:headers,body:payload); break;
      default: response = await http.get(uri,headers:headers);
    }
    Map<String,dynamic> data = {};
    try { data = response.body.isEmpty ? {} : Map<String,dynamic>.from(jsonDecode(response.body)); }
    catch (_) { throw Exception('استجابة غير صالحة من السيرفر (${response.statusCode})'); }
    if(response.statusCode >= 400 || data['ok'] == false){
      throw Exception((data['message'] ?? 'حدث خطأ في الاتصال').toString());
    }
    return data;
  }

  static Future<void> login(String email,String password) async {
    final j=await request('/login',method:'POST',body:{'email':email,'password':password});
    token=j['token']?.toString();
    if(token==null) throw Exception('لم يتم استلام رمز الدخول');
    await (await SharedPreferences.getInstance()).setString('token',token!);
  }
  static Future<void> register(String name,String email,String password) async {
    final j=await request('/register',method:'POST',body:{'name':name,'email':email,'password':password});
    token=j['token']?.toString();
    if(token==null) throw Exception('لم يتم استلام رمز الدخول');
    await (await SharedPreferences.getInstance()).setString('token',token!);
  }
  static Future<void> logout() async {
    token=null;
    await (await SharedPreferences.getInstance()).remove('token');
  }
  static String image(String? value){
    if(value==null || value.isEmpty) return '';
    return value.startsWith('http') ? value : '$origin$value';
  }
  static Future<void> uploadAvatar(File file) async {
    final req=http.MultipartRequest('POST',Uri.parse('$base/avatar'));
    if(token!=null) req.headers['Authorization']='Bearer $token';
    req.files.add(await http.MultipartFile.fromPath('avatar',file.path));
    final streamed=await req.send();
    final raw=await streamed.stream.bytesToString();
    if(streamed.statusCode>=400){
      try{throw Exception(jsonDecode(raw)['message']??'فشل رفع الصورة');}catch(_){throw Exception('فشل رفع الصورة');}
    }
  }
}

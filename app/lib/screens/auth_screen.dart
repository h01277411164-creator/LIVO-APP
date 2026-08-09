import 'package:flutter/material.dart';
import '../brand.dart';
import '../services/api.dart';
import 'home_shell.dart';
class AuthScreen extends StatefulWidget{const AuthScreen({super.key});@override State<AuthScreen>createState()=>_AuthScreenState();}
class _AuthScreenState extends State<AuthScreen>{
  bool registerMode=false,busy=false; String error='';
  final name=TextEditingController(),email=TextEditingController(),pass=TextEditingController();
  Future<void> submit() async {setState(()=>busy=true);try{if(registerMode){await Api.register(name.text.trim(),email.text.trim(),pass.text);}else{await Api.login(email.text.trim(),pass.text);}if(mounted)Navigator.pushReplacement(context,MaterialPageRoute(builder:(_)=>const HomeShell()));}catch(e){if(mounted)setState(()=>error=e.toString().replaceFirst('Exception: ',''));}finally{if(mounted)setState(()=>busy=false);}}
  @override Widget build(BuildContext context)=>Scaffold(body:SafeArea(child:Center(child:SingleChildScrollView(padding:const EdgeInsets.all(28),child:ConstrainedBox(constraints:const BoxConstraints(maxWidth:430),child:Column(children:[
    const Icon(Brand.logo,size:72,color:Color(0xfffe2c55)),const Text(Brand.name,style:TextStyle(fontSize:38,fontWeight:FontWeight.w900)),const SizedBox(height:30),
    if(registerMode)...[TextField(controller:name,decoration:const InputDecoration(labelText:'الاسم')),const SizedBox(height:12)],
    TextField(controller:email,keyboardType:TextInputType.emailAddress,decoration:const InputDecoration(labelText:'البريد الإلكتروني')),const SizedBox(height:12),
    TextField(controller:pass,obscureText:true,decoration:const InputDecoration(labelText:'كلمة المرور')),const SizedBox(height:12),
    if(error.isNotEmpty)Padding(padding:const EdgeInsets.only(bottom:10),child:Text(error,style:const TextStyle(color:Colors.redAccent))),
    SizedBox(width:double.infinity,height:50,child:FilledButton(onPressed:busy?null:submit,child:Text(busy?'جاري التحميل...':registerMode?'إنشاء حساب':'تسجيل الدخول'))),
    TextButton(onPressed:()=>setState((){registerMode=!registerMode;error='';}),child:Text(registerMode?'لديك حساب؟ تسجيل الدخول':'إنشاء حساب جديد')),
  ]))))));
}

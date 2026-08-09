import 'dart:async';
import 'package:flutter/material.dart';
import '../services/api.dart';
import 'live_list_screen.dart';
import 'discover_screen.dart';
import 'messages_screen.dart';
import 'profile_screen.dart';
import 'call_screen.dart';
class HomeShell extends StatefulWidget{const HomeShell({super.key});@override State<HomeShell>createState()=>_HomeShellState();}
class _HomeShellState extends State<HomeShell>{
  int index=0; Timer? timer; int? lastCall;
  final pages=const[LiveListScreen(),DiscoverScreen(),MessagesScreen(),ProfileScreen()];
  @override void initState(){super.initState();timer=Timer.periodic(const Duration(seconds:3),(_)=>checkCall());}
  Future<void> checkCall()async{try{final j=await Api.request('/call/pending');final c=j['call'];if(c==null||!mounted)return;final id=int.tryParse(c['id'].toString());if(id==null||id==lastCall)return;lastCall=id;final yes=await showDialog<bool>(context:context,builder:(ctx)=>AlertDialog(title:const Text('مكالمة فيديو واردة'),content:Text('${c['caller_name']??'مستخدم'} يتصل بك'),actions:[TextButton(onPressed:()=>Navigator.pop(ctx,false),child:const Text('رفض')),FilledButton(onPressed:()=>Navigator.pop(ctx,true),child:const Text('رد'))]));if(yes==true&&mounted){Navigator.push(context,MaterialPageRoute(builder:(_)=>CallScreen(incomingCall:Map<String,dynamic>.from(c))));}else{await Api.request('/call/end',method:'POST',body:{'call_id':id});}}catch(_){}}
  @override void dispose(){timer?.cancel();super.dispose();}
  @override Widget build(BuildContext context)=>Scaffold(body:IndexedStack(index:index,children:pages),bottomNavigationBar:NavigationBar(selectedIndex:index,onDestinationSelected:(v)=>setState(()=>index=v),destinations:const[
    NavigationDestination(icon:Icon(Icons.live_tv_outlined),selectedIcon:Icon(Icons.live_tv),label:'البث'),
    NavigationDestination(icon:Icon(Icons.explore_outlined),selectedIcon:Icon(Icons.explore),label:'اكتشاف'),
    NavigationDestination(icon:Icon(Icons.chat_bubble_outline),selectedIcon:Icon(Icons.chat_bubble),label:'الرسائل'),
    NavigationDestination(icon:Icon(Icons.person_outline),selectedIcon:Icon(Icons.person),label:'حسابي'),
  ]));
}

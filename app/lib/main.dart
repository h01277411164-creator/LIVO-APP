import 'package:flutter/material.dart';
import 'brand.dart';
import 'services/api.dart';
import 'screens/auth_screen.dart';
import 'screens/home_shell.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Api.init();
  runApp(const LivoApp());
}

class LivoApp extends StatelessWidget{
  const LivoApp({super.key});
  @override Widget build(BuildContext context){
    return MaterialApp(
      debugShowCheckedModeBanner:false,
      title:Brand.name,
      theme:ThemeData(
        useMaterial3:true,
        colorScheme:ColorScheme.fromSeed(seedColor:const Color(0xfffe2c55),brightness:Brightness.dark),
        scaffoldBackgroundColor:const Color(0xff0c0c0f),
        appBarTheme:const AppBarTheme(backgroundColor:Color(0xff0c0c0f),foregroundColor:Colors.white),
        navigationBarTheme:const NavigationBarThemeData(backgroundColor:Color(0xff111116)),
        inputDecorationTheme:InputDecorationTheme(filled:true,fillColor:const Color(0xff17171d),border:OutlineInputBorder(borderRadius:BorderRadius.circular(16),borderSide:BorderSide.none)),
      ),
      home:Api.token==null?const AuthScreen():const HomeShell(),
    );
  }
}

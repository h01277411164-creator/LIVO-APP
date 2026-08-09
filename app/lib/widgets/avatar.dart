import 'package:flutter/material.dart';
import '../services/api.dart';
class Avatar extends StatelessWidget{
  final String? url; final double radius;
  const Avatar({super.key,this.url,this.radius=24});
  @override Widget build(BuildContext context){
    final u=Api.image(url);
    return CircleAvatar(radius:radius,backgroundColor:Colors.grey.shade300,
      backgroundImage:u.isNotEmpty?NetworkImage(u):null,
      child:u.isEmpty?Icon(Icons.person,size:radius,color:Colors.grey.shade700):null);
  }
}

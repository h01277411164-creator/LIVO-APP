import 'package:flutter/material.dart';
import '../services/api.dart';
import '../widgets/avatar.dart';
import 'user_profile_screen.dart';

class DiscoverScreen extends StatefulWidget {
  const DiscoverScreen({super.key});
  @override
  State<DiscoverScreen> createState() => _DiscoverScreenState();
}

class _DiscoverScreenState extends State<DiscoverScreen> {
  List users = [];
  bool loading = true;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final j = await Api.request('/users');
      users = List.from(j['users'] ?? []);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('اكتشاف')),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: load,
              child: ListView.separated(
                padding: const EdgeInsets.all(10),
                itemCount: users.length,
                separatorBuilder: (_, __) => const Divider(height: 1),
                itemBuilder: (_, i) {
                  final u = Map<String, dynamic>.from(users[i]);
                  return ListTile(
                    leading: Avatar(url: u['avatar']),
                    title: Text(u['name'] ?? 'مستخدم'),
                    subtitle: Text(u['bio'] ?? ''),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute(builder: (_) => UserProfileScreen(user: u)),
                    ),
                  );
                },
              ),
            ),
    );
  }
}

import 'package:flutter/material.dart';
import '../services/api.dart';
import '../widgets/avatar.dart';
import 'live_room_screen.dart';

class LiveListScreen extends StatefulWidget {
  const LiveListScreen({super.key});
  @override
  State<LiveListScreen> createState() => _LiveListScreenState();
}

class _LiveListScreenState extends State<LiveListScreen> {
  List rooms = [];
  bool loading = true;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final j = await Api.request('/live');
      rooms = List.from(j['rooms'] ?? []);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> start() async {
    final c = TextEditingController(text: 'بث مباشر');
    final title = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('بدء بث مباشر'),
        content: TextField(controller: c, decoration: const InputDecoration(labelText: 'عنوان البث')),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('إلغاء')),
          FilledButton(onPressed: () => Navigator.pop(ctx, c.text.trim()), child: const Text('بدء')),
        ],
      ),
    );
    if (title == null || title.isEmpty) return;
    final j = await Api.request('/live/create', method: 'POST', body: {'title': title});
    final room = {'id': j['room_id'], 'title': title, 'host_name': 'أنا', 'host_id': -1};
    if (mounted) {
      Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => LiveRoomScreen(room: room, isHost: true)),
      ).then((_) => load());
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('LIVE'),
        actions: [IconButton.filled(onPressed: start, icon: const Icon(Icons.add))],
      ),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: load,
              child: GridView.builder(
                padding: const EdgeInsets.all(10),
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: 2,
                  childAspectRatio: .72,
                  crossAxisSpacing: 10,
                  mainAxisSpacing: 10,
                ),
                itemCount: rooms.length,
                itemBuilder: (_, i) {
                  final r = Map<String, dynamic>.from(rooms[i]);
                  return InkWell(
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute(builder: (_) => LiveRoomScreen(room: r, isHost: false)),
                    ),
                    child: Container(
                      decoration: BoxDecoration(
                        color: const Color(0xff18181f),
                        borderRadius: BorderRadius.circular(18),
                      ),
                      child: Stack(
                        children: [
                          const Positioned.fill(
                            child: Center(child: Icon(Icons.videocam, size: 70, color: Colors.white24)),
                          ),
                          Positioned(
                            left: 10,
                            right: 10,
                            bottom: 10,
                            child: Row(
                              children: [
                                Avatar(url: r['avatar'], radius: 18),
                                const SizedBox(width: 7),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        r['host_name'] ?? 'مستخدم',
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                        style: const TextStyle(fontWeight: FontWeight.bold),
                                      ),
                                      Text(r['title'] ?? 'LIVE', maxLines: 1, overflow: TextOverflow.ellipsis),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  );
                },
              ),
            ),
    );
  }
}

<?php
require dirname(__DIR__) . '/config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

function resolved_route() {
    // First choice: route supplied by .htaccess.
    if (isset($_GET['route']) && $_GET['route'] !== '') return trim((string)$_GET['route'], '/');

    // Fallback for hosts where query-string rewrite is lost.
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/api/index.php'));
    if ($scriptDir !== '/' && strpos($uri, $scriptDir . '/') === 0) {
        $uri = substr($uri, strlen($scriptDir) + 1);
    } elseif (strpos($uri, '/api/') !== false) {
        $uri = substr($uri, strpos($uri, '/api/') + 5);
    } elseif (substr($uri, -4) === '/api') {
        $uri = '';
    }
    return trim($uri, '/');
}

$route = resolved_route();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

function method_not_allowed($allow) {
    header('Allow: ' . $allow);
    json_response(['ok'=>false,'message'=>'Method not allowed','allow'=>$allow],405);
}

if ($route === '' && $method === 'GET') {
    json_response(['ok'=>true,'service'=>'LIVO PHP API','version'=>'2.0','domain'=>APP_URL]);
}

if ($route === 'health' && $method === 'GET') {
    try {
        db()->query('SELECT 1');
        json_response(['ok'=>true,'service'=>'LIVO PHP API','php'=>PHP_VERSION,'domain'=>APP_URL,'version'=>'2.0']);
    } catch (Throwable $e) {
        json_response(['ok'=>false,'message'=>'Database not configured','error'=>$e->getMessage()],500);
    }
}

if ($route === 'routes' && $method === 'GET') {
    json_response(['ok'=>true,'routes'=>[
        'POST /api/register','POST /api/login','GET /api/me','POST /api/profile','POST /api/avatar',
        'GET /api/users','GET /api/user/{id}','POST /api/follow','POST /api/unfollow',
        'GET /api/conversations','POST /api/conversation/create','GET /api/messages/{id}','POST /api/messages/send',
        'GET /api/live','POST /api/live/create','POST /api/live/end','POST /api/live/join','POST /api/live/signal','GET /api/live/signals','POST /api/live/comment','GET /api/live/comments',
        'POST /api/call/start','GET /api/call/pending','POST /api/call/answer','GET /api/call/status','POST /api/call/end','POST /api/report'
    ]]);
}

if ($route === 'register') {
    if ($method !== 'POST') method_not_allowed('POST');
    $d = input_json();
    $name = trim($d['name'] ?? '');
    $email = strtolower(trim($d['email'] ?? ''));
    $pass = (string)($d['password'] ?? '');
    if ($name === '' || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($pass) < 6) {
        json_response(['ok'=>false,'message'=>'بيانات التسجيل غير صحيحة'],422);
    }
    try {
        $st = db()->prepare('INSERT INTO users(name,email,password_hash,created_at) VALUES(?,?,?,NOW())');
        $st->execute([$name,$email,password_hash($pass,PASSWORD_DEFAULT)]);
        $uid = (int)db()->lastInsertId();
        $token = random_token();
        db()->prepare('INSERT INTO auth_tokens(user_id,token,expires_at,created_at) VALUES(?,?,DATE_ADD(NOW(), INTERVAL 30 DAY),NOW())')->execute([$uid,$token]);
        $st = db()->prepare('SELECT * FROM users WHERE id=? LIMIT 1');
        $st->execute([$uid]);
        json_response(['ok'=>true,'token'=>$token,'user'=>public_user($st->fetch())],201);
    } catch (PDOException $e) {
        if ((string)$e->getCode() === '23000') json_response(['ok'=>false,'message'=>'البريد الإلكتروني مستخدم بالفعل'],409);
        json_response(['ok'=>false,'message'=>'تعذر إنشاء الحساب','error'=>$e->getMessage()],500);
    }
}

if ($route === 'login') {
    if ($method !== 'POST') method_not_allowed('POST');
    $d=input_json(); $email=strtolower(trim($d['email']??'')); $pass=(string)($d['password']??'');
    $st=db()->prepare('SELECT * FROM users WHERE email=? LIMIT 1'); $st->execute([$email]); $u=$st->fetch();
    if(!$u || !password_verify($pass,$u['password_hash']) || (int)$u['is_blocked']===1) json_response(['ok'=>false,'message'=>'بيانات الدخول غير صحيحة'],401);
    $token=random_token();
    db()->prepare('INSERT INTO auth_tokens(user_id,token,expires_at,created_at) VALUES(?,?,DATE_ADD(NOW(), INTERVAL 30 DAY),NOW())')->execute([$u['id'],$token]);
    json_response(['ok'=>true,'token'=>$token,'user'=>public_user($u)]);
}

if ($route === 'me') {
    if ($method !== 'GET') method_not_allowed('GET');
    $u=require_user(); json_response(['ok'=>true,'user'=>public_user($u)]);
}

if ($route === 'profile') {
    if ($method !== 'POST') method_not_allowed('POST');
    $u=require_user(); $d=input_json();
    $name=trim($d['name']??$u['name']); $bio=trim($d['bio']??($u['bio']??''));
    db()->prepare('UPDATE users SET name=?,bio=?,updated_at=NOW() WHERE id=?')->execute([$name,$bio,$u['id']]);
    json_response(['ok'=>true]);
}

if ($route === 'avatar') {
    if ($method !== 'POST') method_not_allowed('POST');
    $u=require_user();
    if(empty($_FILES['avatar']['tmp_name'])) json_response(['ok'=>false,'message'=>'لم يتم اختيار صورة'],422);
    if(!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR,0755,true);
    $tmp=$_FILES['avatar']['tmp_name'];
    $mime=function_exists('mime_content_type') ? @mime_content_type($tmp) : ($_FILES['avatar']['type']??'');
    $map=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if(!isset($map[$mime])) json_response(['ok'=>false,'message'=>'نوع الصورة غير مدعوم'],422);
    $filename='avatar_'.$u['id'].'_'.time().'.'.$map[$mime];
    if(!move_uploaded_file($tmp,UPLOAD_DIR.$filename)) json_response(['ok'=>false,'message'=>'تعذر حفظ الصورة'],500);
    $url='/uploads/'.$filename;
    db()->prepare('UPDATE users SET avatar=?,updated_at=NOW() WHERE id=?')->execute([$url,$u['id']]);
    json_response(['ok'=>true,'avatar'=>$url]);
}

if ($route === 'users') {
    if ($method !== 'GET') method_not_allowed('GET');
    $u=require_user();
    $st=db()->prepare('SELECT id,name,bio,avatar,created_at FROM users WHERE id<>? AND is_blocked=0 ORDER BY id DESC LIMIT 100');
    $st->execute([$u['id']]); json_response(['ok'=>true,'users'=>$st->fetchAll()]);
}

if (preg_match('#^user/(\d+)$#',$route,$m)) {
    if ($method !== 'GET') method_not_allowed('GET');
    $me=require_user(); $id=(int)$m[1];
    $st=db()->prepare('SELECT id,name,bio,avatar,created_at FROM users WHERE id=? AND is_blocked=0 LIMIT 1'); $st->execute([$id]); $u=$st->fetch();
    if(!$u) json_response(['ok'=>false,'message'=>'المستخدم غير موجود'],404);
    $f=db()->prepare('SELECT 1 FROM follows WHERE follower_id=? AND following_id=?'); $f->execute([$me['id'],$id]);
    json_response(['ok'=>true,'user'=>$u,'is_following'=>(bool)$f->fetchColumn()]);
}

if ($route === 'follow' || $route === 'unfollow') {
    if ($method !== 'POST') method_not_allowed('POST');
    $u=require_user(); $d=input_json(); $target=(int)($d['user_id']??0);
    if(!$target || $target===(int)$u['id']) json_response(['ok'=>false,'message'=>'مستخدم غير صالح'],422);
    if ($route === 'follow') db()->prepare('INSERT IGNORE INTO follows(follower_id,following_id,created_at) VALUES(?,?,NOW())')->execute([$u['id'],$target]);
    else db()->prepare('DELETE FROM follows WHERE follower_id=? AND following_id=?')->execute([$u['id'],$target]);
    json_response(['ok'=>true]);
}

if ($route === 'conversations') {
    if ($method !== 'GET') method_not_allowed('GET');
    $u=require_user();
    $sql='SELECT c.id,c.updated_at, CASE WHEN c.user1_id=? THEN u2.id ELSE u1.id END other_id, CASE WHEN c.user1_id=? THEN u2.name ELSE u1.name END other_name, CASE WHEN c.user1_id=? THEN u2.avatar ELSE u1.avatar END other_avatar FROM conversations c JOIN users u1 ON u1.id=c.user1_id JOIN users u2 ON u2.id=c.user2_id WHERE c.user1_id=? OR c.user2_id=? ORDER BY c.updated_at DESC';
    $st=db()->prepare($sql); $st->execute([$u['id'],$u['id'],$u['id'],$u['id'],$u['id']]);
    json_response(['ok'=>true,'conversations'=>$st->fetchAll()]);
}

if ($route === 'conversation/create') {
    if ($method !== 'POST') method_not_allowed('POST');
    $u=require_user(); $d=input_json(); $other=(int)($d['user_id']??0);
    if(!$other || $other===(int)$u['id']) json_response(['ok'=>false,'message'=>'مستخدم غير صالح'],422);
    $a=min((int)$u['id'],$other); $b=max((int)$u['id'],$other);
    $st=db()->prepare('SELECT id FROM conversations WHERE user1_id=? AND user2_id=?'); $st->execute([$a,$b]); $row=$st->fetch();
    if(!$row){ db()->prepare('INSERT INTO conversations(user1_id,user2_id,created_at,updated_at) VALUES(?,?,NOW(),NOW())')->execute([$a,$b]); $id=(int)db()->lastInsertId(); }
    else $id=(int)$row['id'];
    json_response(['ok'=>true,'conversation_id'=>$id]);
}

if (preg_match('#^messages/(\d+)$#',$route,$m)) {
    if ($method !== 'GET') method_not_allowed('GET');
    $u=require_user(); $cid=(int)$m[1];
    $st=db()->prepare('SELECT 1 FROM conversations WHERE id=? AND (user1_id=? OR user2_id=?)'); $st->execute([$cid,$u['id'],$u['id']]);
    if(!$st->fetch()) json_response(['ok'=>false,'message'=>'غير مصرح'],403);
    $st=db()->prepare('SELECT m.id,m.sender_id,m.body,m.created_at,u.name sender_name FROM messages m JOIN users u ON u.id=m.sender_id WHERE conversation_id=? ORDER BY m.id ASC LIMIT 500');
    $st->execute([$cid]); json_response(['ok'=>true,'messages'=>$st->fetchAll()]);
}

if ($route === 'messages/send') {
    if ($method !== 'POST') method_not_allowed('POST');
    $u=require_user(); $d=input_json(); $cid=(int)($d['conversation_id']??0); $body=trim($d['body']??'');
    if($body==='') json_response(['ok'=>false,'message'=>'الرسالة فارغة'],422);
    $st=db()->prepare('SELECT 1 FROM conversations WHERE id=? AND (user1_id=? OR user2_id=?)'); $st->execute([$cid,$u['id'],$u['id']]);
    if(!$st->fetch()) json_response(['ok'=>false,'message'=>'غير مصرح'],403);
    db()->prepare('INSERT INTO messages(conversation_id,sender_id,body,created_at) VALUES(?,?,?,NOW())')->execute([$cid,$u['id'],$body]);
    $mid=(int)db()->lastInsertId();
    db()->prepare('UPDATE conversations SET updated_at=NOW() WHERE id=?')->execute([$cid]);
    json_response(['ok'=>true,'message_id'=>$mid]);
}

if ($route === 'live') {
    if ($method !== 'GET') method_not_allowed('GET');
    $st=db()->query('SELECT l.id,l.title,l.status,l.created_at,u.id host_id,u.name host_name,u.avatar FROM live_rooms l JOIN users u ON u.id=l.host_id WHERE l.status="live" ORDER BY l.id DESC LIMIT 100');
    json_response(['ok'=>true,'rooms'=>$st->fetchAll()]);
}

if ($route === 'live/create') {
    if ($method !== 'POST') method_not_allowed('POST');
    $u=require_user(); $d=input_json(); $title=trim($d['title']??'LIVE'); $key=random_token();
    db()->prepare('INSERT INTO live_rooms(host_id,title,room_key,status,created_at) VALUES(?,?,?,"live",NOW())')->execute([$u['id'],$title,$key]);
    json_response(['ok'=>true,'room_id'=>(int)db()->lastInsertId(),'room_key'=>$key]);
}

if ($route === 'live/end') {
    if ($method !== 'POST') method_not_allowed('POST');
    $u=require_user(); $d=input_json(); $id=(int)($d['room_id']??0);
    db()->prepare('UPDATE live_rooms SET status="ended",ended_at=NOW() WHERE id=? AND host_id=?')->execute([$id,$u['id']]);
    json_response(['ok'=>true]);
}

if ($route === 'live/join') {
    if ($method !== 'POST') method_not_allowed('POST');
    $u=require_user(); $d=input_json(); $room=(int)($d['room_id']??0);
    $st=db()->prepare('SELECT host_id,status FROM live_rooms WHERE id=?'); $st->execute([$room]); $r=$st->fetch();
    if(!$r || $r['status']!=='live') json_response(['ok'=>false,'message'=>'البث غير متاح'],404);
    db()->prepare('INSERT INTO live_signals(room_id,sender_id,target_id,type,payload,created_at) VALUES(?,?,?,"viewer_join",NULL,NOW())')->execute([$room,$u['id'],$r['host_id']]);
    json_response(['ok'=>true,'host_id'=>(int)$r['host_id']]);
}

if ($route === 'live/signal') {
    if ($method !== 'POST') method_not_allowed('POST');
    $u=require_user(); $d=input_json(); $room=(int)($d['room_id']??0); $target=(int)($d['target_id']??0); $type=substr(trim($d['type']??''),0,40);
    if(!$room || !$target || $type==='') json_response(['ok'=>false,'message'=>'إشارة غير صالحة'],422);
    $payload=json_encode($d['payload']??null,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    db()->prepare('INSERT INTO live_signals(room_id,sender_id,target_id,type,payload,created_at) VALUES(?,?,?,?,?,NOW())')->execute([$room,$u['id'],$target,$type,$payload]);
    json_response(['ok'=>true]);
}

if ($route === 'live/signals') {
    if ($method !== 'GET') method_not_allowed('GET');
    $u=require_user(); $room=(int)($_GET['room_id']??0); $after=(int)($_GET['after']??0);
    $st=db()->prepare('SELECT id,room_id,sender_id,target_id,type,payload,created_at FROM live_signals WHERE room_id=? AND id>? AND (target_id=? OR target_id IS NULL) ORDER BY id ASC LIMIT 100');
    $st->execute([$room,$after,$u['id']]); json_response(['ok'=>true,'signals'=>$st->fetchAll()]);
}

if ($route === 'live/comment') {
    if ($method !== 'POST') method_not_allowed('POST');
    $u=require_user(); $d=input_json(); $room=(int)($d['room_id']??0); $body=trim($d['body']??'');
    if(!$room || $body==='') json_response(['ok'=>false,'message'=>'التعليق فارغ'],422);
    db()->prepare('INSERT INTO live_comments(room_id,user_id,body,created_at) VALUES(?,?,?,NOW())')->execute([$room,$u['id'],$body]);
    json_response(['ok'=>true]);
}

if ($route === 'live/comments') {
    if ($method !== 'GET') method_not_allowed('GET');
    $room=(int)($_GET['room_id']??0);
    $st=db()->prepare('SELECT c.id,c.body,c.created_at,u.id user_id,u.name,u.avatar FROM live_comments c JOIN users u ON u.id=c.user_id WHERE c.room_id=? ORDER BY c.id DESC LIMIT 100');
    $st->execute([$room]); json_response(['ok'=>true,'comments'=>array_reverse($st->fetchAll())]);
}

if ($route === 'call/start') {
    if ($method !== 'POST') method_not_allowed('POST');
    $u=require_user(); $d=input_json(); $to=(int)($d['user_id']??0); $offer=$d['offer']??null;
    if(!$to || !$offer) json_response(['ok'=>false,'message'=>'بيانات المكالمة غير مكتملة'],422);
    db()->prepare('INSERT INTO calls(caller_id,callee_id,offer,status,created_at) VALUES(?,?,?,"ringing",NOW())')->execute([$u['id'],$to,json_encode($offer,JSON_UNESCAPED_SLASHES)]);
    json_response(['ok'=>true,'call_id'=>(int)db()->lastInsertId()]);
}

if ($route === 'call/pending') {
    if ($method !== 'GET') method_not_allowed('GET');
    $u=require_user();
    $st=db()->prepare('SELECT c.*,u.name caller_name,u.avatar caller_avatar FROM calls c JOIN users u ON u.id=c.caller_id WHERE c.callee_id=? AND c.status="ringing" ORDER BY c.id DESC LIMIT 1');
    $st->execute([$u['id']]); json_response(['ok'=>true,'call'=>$st->fetch()?:null]);
}

if ($route === 'call/answer') {
    if ($method !== 'POST') method_not_allowed('POST');
    $u=require_user(); $d=input_json(); $id=(int)($d['call_id']??0); $answer=$d['answer']??null;
    if(!$answer) json_response(['ok'=>false,'message'=>'الرد غير صالح'],422);
    db()->prepare('UPDATE calls SET answer=?,status="active",answered_at=NOW() WHERE id=? AND callee_id=?')->execute([json_encode($answer,JSON_UNESCAPED_SLASHES),$id,$u['id']]);
    json_response(['ok'=>true]);
}

if ($route === 'call/status') {
    if ($method !== 'GET') method_not_allowed('GET');
    $u=require_user(); $id=(int)($_GET['id']??0);
    $st=db()->prepare('SELECT * FROM calls WHERE id=? AND (caller_id=? OR callee_id=?)'); $st->execute([$id,$u['id'],$u['id']]);
    json_response(['ok'=>true,'call'=>$st->fetch()?:null]);
}

if ($route === 'call/end') {
    if ($method !== 'POST') method_not_allowed('POST');
    $u=require_user(); $d=input_json(); $id=(int)($d['call_id']??0);
    db()->prepare('UPDATE calls SET status="ended",ended_at=NOW() WHERE id=? AND (caller_id=? OR callee_id=?)')->execute([$id,$u['id'],$u['id']]);
    json_response(['ok'=>true]);
}

if ($route === 'report') {
    if ($method !== 'POST') method_not_allowed('POST');
    $u=require_user(); $d=input_json(); $target=(int)($d['user_id']??0); $reason=trim($d['reason']??'');
    if(!$target || $reason==='') json_response(['ok'=>false,'message'=>'بيانات البلاغ غير مكتملة'],422);
    db()->prepare('INSERT INTO reports(reporter_id,target_user_id,reason,status,created_at) VALUES(?,?,?,"open",NOW())')->execute([$u['id'],$target,$reason]);
    json_response(['ok'=>true]);
}

json_response(['ok'=>false,'message'=>'Route not found','route'=>$route,'method'=>$method],404);

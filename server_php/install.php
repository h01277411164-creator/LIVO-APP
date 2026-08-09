<?php
$msg=''; $ok=false;
if($_SERVER['REQUEST_METHOD']==='POST'){
  $host=trim($_POST['db_host']??'localhost'); $dbn=trim($_POST['db_name']??''); $user=trim($_POST['db_user']??''); $pass=(string)($_POST['db_pass']??'');
  $adminUser=trim($_POST['admin_user']??'admin'); $adminPass=(string)($_POST['admin_pass']??'');
  try{
    if($dbn===''||$user===''||strlen($adminPass)<6) throw new RuntimeException('أكمل جميع البيانات وكلمة مرور الأدمن 6 أحرف على الأقل.');
    $pdo=new PDO('mysql:host='.$host.';dbname='.$dbn.';charset=utf8mb4',$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $pdo->exec(file_get_contents(__DIR__.'/database.sql'));
    $hash=password_hash($adminPass,PASSWORD_DEFAULT);
    $st=$pdo->prepare('INSERT INTO admins(username,password_hash,created_at) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash)');
    $st->execute([$adminUser,$hash]);
    $local="<?php\n";
    $local.="define('DB_HOST', ".var_export($host,true).");\n";
    $local.="define('DB_NAME', ".var_export($dbn,true).");\n";
    $local.="define('DB_USER', ".var_export($user,true).");\n";
    $local.="define('DB_PASS', ".var_export($pass,true).");\n";
    if(file_put_contents(__DIR__.'/config.local.php',$local)===false) throw new RuntimeException('تعذر كتابة config.local.php. اجعل المجلد قابلًا للكتابة مؤقتًا.');
    $ok=true; $msg='تم التثبيت/التحديث بنجاح. اختبر /api/health ثم احذف install.php.';
  }catch(Throwable $e){$msg='خطأ: '.$e->getMessage();}
}
?><!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>LIVO Installer</title><style>body{font-family:Arial;background:#0d0d10;color:#fff;margin:0;padding:30px}.box{max-width:600px;margin:auto;background:#18181f;padding:26px;border-radius:18px}input{width:100%;box-sizing:border-box;padding:13px;margin:7px 0 15px;border-radius:10px;border:1px solid #444;background:#101015;color:#fff}button{padding:13px 22px;border:0;border-radius:10px;background:#fe2c55;color:#fff;font-weight:bold}.msg{padding:13px;background:#25252d;border-radius:10px;margin:12px 0}.ok{border:1px solid #3c7}</style></head><body><div class="box"><h2>LIVO — تثبيت/تحديث السيرفر</h2><p>الدومين: <b>https://bbb.ezzy500.vip</b></p><?php if($msg):?><div class="msg <?=$ok?'ok':''?>"><?=htmlspecialchars($msg)?></div><?php endif;?><form method="post"><label>DB Host</label><input name="db_host" value="localhost"><label>Database Name</label><input name="db_name" required><label>Database User</label><input name="db_user" required><label>Database Password</label><input name="db_pass" type="password"><hr><label>اسم دخول الأدمن</label><input name="admin_user" value="admin" required><label>كلمة مرور الأدمن</label><input name="admin_pass" type="password" required minlength="6"><button>تثبيت / تحديث LIVO</button></form></div></body></html>

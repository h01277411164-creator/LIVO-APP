LIVO - نسخة cPanel PHP 7.4 الكاملة
الدومين: https://bbb.ezzy500.vip

طريقة الاستبدال من الصفر:
1) خذ نسخة احتياطية من ملفات bbb.ezzy500.vip الحالية.
2) ارفع محتويات هذا ZIP داخل مجلد bbb.ezzy500.vip ووافق على الاستبدال.
3) افتح https://bbb.ezzy500.vip/install.php
4) أدخل نفس بيانات MySQL الموجودة عندك، واسم/باسورد الأدمن.
   - التثبيت لا يحذف البيانات القديمة؛ يستخدم CREATE TABLE IF NOT EXISTS.
5) بعد نجاح التثبيت اختبر:
   https://bbb.ezzy500.vip/api/health
   https://bbb.ezzy500.vip/api/routes
6) افتح https://bbb.ezzy500.vip/api/register من المتصفح.
   الطبيعي الآن أن يظهر Method not allowed لأن التسجيل يحتاج POST، وهذا يؤكد أن Route موجود.
7) جرّب إنشاء حساب من التطبيق.
8) بعد التأكد احذف install.php من السيرفر.

مهم:
- لا تحتاج Node.js أو npm.
- التسجيل: POST /api/register
- الدخول: POST /api/login
- لوحة الأدمن: https://bbb.ezzy500.vip/admin/

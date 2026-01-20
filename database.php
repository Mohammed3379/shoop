<?php
$servername = "localhost";
$username = "root"; 
$password = ""; 
$dbname = "myshop";

// إخفاء أخطاء PHP الافتراضية عن المستخدم (للحماية)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // إنشاء الاتصال
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    // تحسين دعم اللغة العربية والرموز التعبيرية
    $conn->set_charset("utf8mb4");

} catch (mysqli_sql_exception $e) {
    // في حالة الفشل، لا نظهر الخطأ التقني للعميل
    // بدلاً من ذلك، يمكننا تسجيله في ملف (log) وعرض رسالة بسيطة
    error_log($e->getMessage()); // تسجيل الخطأ في ملف السيرفر
    die("عذراً، حدثت مشكلة في الاتصال بقاعدة البيانات. حاول لاحقاً.");
}

// تضمين إعدادات العملة
include_once __DIR__ . '/currency.php';
?>
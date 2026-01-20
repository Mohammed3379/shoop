<?php
/**
 * ملف اختبار الاتصال بقاعدة البيانات
 * استخدم هذا الملف للتحقق من أن كل شيء يعمل بشكل صحيح
 */

echo "<!DOCTYPE html>
<html lang='ar' dir='rtl'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>اختبار الاتصال</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; text-align: center; }
        .test { margin: 20px 0; padding: 15px; border-left: 4px solid #ddd; background: #f9f9f9; }
        .test.success { border-left-color: #28a745; background: #d4edda; }
        .test.error { border-left-color: #dc3545; background: #f8d7da; }
        .test.warning { border-left-color: #ffc107; background: #fff3cd; }
        .test h3 { margin: 0 0 10px 0; }
        .test p { margin: 5px 0; font-size: 14px; }
        .icon { font-size: 20px; margin-right: 10px; }
        code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 اختبار الاتصال والملفات</h1>";

// 1. اختبار الاتصال بقاعدة البيانات
echo "<div class='test";
try {
    include 'app/config/database.php';
    if ($conn && !$conn->connect_error) {
        echo " success'><h3><span class='icon'>✅</span>قاعدة البيانات</h3>";
        echo "<p>الاتصال: <strong>نجح</strong></p>";
        echo "<p>الخادم: <code>" . $conn->server_info . "</code></p>";
        echo "<p>قاعدة البيانات: <code>myshop</code></p>";
        
        // اختبار الجداول
        $tables = ['users', 'products', 'orders'];
        foreach ($tables as $table) {
            $result = $conn->query("SHOW TABLES LIKE '$table'");
            if ($result && $result->num_rows > 0) {
                echo "<p>✓ جدول <code>$table</code> موجود</p>";
            } else {
                echo "<p>✗ جدول <code>$table</code> <strong>غير موجود</strong></p>";
            }
        }
    } else {
        echo " error'><h3><span class='icon'>❌</span>قاعدة البيانات</h3>";
        echo "<p>الاتصال: <strong>فشل</strong></p>";
        echo "<p>الخطأ: " . $conn->connect_error . "</p>";
    }
} catch (Exception $e) {
    echo " error'><h3><span class='icon'>❌</span>قاعدة البيانات</h3>";
    echo "<p>الخطأ: " . $e->getMessage() . "</p>";
}
echo "</div>";

// 2. اختبار الملفات المطلوبة
echo "<div class='test";
$required_files = [
    'checkout.php' => 'صفحة الدفع',
    'save_order.php' => 'معالج حفظ الطلب',
    'app/config/database.php' => 'ملف الاتصال',
    'header.php' => 'الرأس',
    'footer.php' => 'الفوتر'
];

$all_exist = true;
foreach ($required_files as $file => $name) {
    if (!file_exists($file)) {
        $all_exist = false;
        break;
    }
}

echo ($all_exist ? " success" : " error") . "'><h3><span class='icon'>" . ($all_exist ? "✅" : "❌") . "</span>الملفات المطلوبة</h3>";
foreach ($required_files as $file => $name) {
    $exists = file_exists($file);
    echo "<p>" . ($exists ? "✓" : "✗") . " <code>$file</code> - $name</p>";
}
echo "</div>";

// 3. اختبار الجلسة
echo "<div class='test";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$session_ok = isset($_SESSION);
echo ($session_ok ? " success" : " warning") . "'><h3><span class='icon'>" . ($session_ok ? "✅" : "⚠️") . "</span>الجلسة</h3>";
echo "<p>حالة الجلسة: " . ($session_ok ? "<strong>تعمل</strong>" : "<strong>لم تبدأ</strong>") . "</p>";
if (isset($_SESSION['user_id'])) {
    echo "<p>المستخدم: <strong>مسجل دخول (ID: " . $_SESSION['user_id'] . ")</strong></p>";
} else {
    echo "<p>المستخدم: <strong>لم يسجل دخول</strong></p>";
}
echo "</div>";

// 4. اختبار الأذونات
echo "<div class='test";
$writable_dirs = [
    'public/uploads' => 'مجلد الرفع',
    'public' => 'المجلد العام'
];

$all_writable = true;
foreach ($writable_dirs as $dir => $name) {
    if (!is_writable($dir)) {
        $all_writable = false;
        break;
    }
}

echo ($all_writable ? " success" : " warning") . "'><h3><span class='icon'>" . ($all_writable ? "✅" : "⚠️") . "</span>الأذونات</h3>";
foreach ($writable_dirs as $dir => $name) {
    $writable = is_writable($dir);
    echo "<p>" . ($writable ? "✓" : "✗") . " <code>$dir</code> - " . ($writable ? "قابل للكتابة" : "غير قابل للكتابة") . "</p>";
}
echo "</div>";

// 5. اختبار PHP
echo "<div class='test success'><h3><span class='icon'>✅</span>PHP</h3>";
echo "<p>الإصدار: <code>" . phpversion() . "</code></p>";
echo "<p>الملحقات المطلوبة:</p>";
$extensions = ['mysqli', 'json', 'session'];
foreach ($extensions as $ext) {
    $loaded = extension_loaded($ext);
    echo "<p>" . ($loaded ? "✓" : "✗") . " <code>$ext</code></p>";
}
echo "</div>";

// 6. ملخص
echo "<div class='test' style='background: #e7f3ff; border-left-color: #0066cc;'>";
echo "<h3>📋 الملخص</h3>";
echo "<p>✅ جميع الاختبارات الأساسية تمت</p>";
echo "<p>👉 الخطوة التالية: اختبر checkout.php</p>";
echo "</div>";

echo "
    </div>
</body>
</html>";
?>

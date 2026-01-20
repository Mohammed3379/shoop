<?php
/**
 * صفحة تسجيل خروج الأدمن
 */

session_start();

// حذف جميع متغيرات الجلسة الخاصة بالأدمن
unset($_SESSION['admin_id']);
unset($_SESSION['admin_name']);
unset($_SESSION['admin_role']);
unset($_SESSION['admin_permissions']);
unset($_SESSION['admin_logged_in']);
unset($_SESSION['admin_last_activity']);
unset($_SESSION['admin_csrf_token']);
unset($_SESSION['admin_login_csrf']);

// تدمير الجلسة بالكامل (اختياري)
// session_destroy();

// إعادة التوجيه لصفحة الدخول
header("Location: admin_login.php");
exit();
?>

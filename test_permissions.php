<?php
/**
 * صفحة اختبار الصلاحيات
 */
include '../app/config/database.php';
include 'admin_auth.php';

checkAdminAuth();

echo "<h2>اختبار الصلاحيات</h2>";
echo "<pre style='background:#1a1a2e; color:#fff; padding:20px; direction:ltr;'>";

echo "=== معلومات الجلسة ===\n";
echo "admin_id: " . ($_SESSION['admin_id'] ?? 'غير موجود') . "\n";
echo "admin_name: " . ($_SESSION['admin_name'] ?? 'غير موجود') . "\n";
echo "admin_role: ";
print_r($_SESSION['admin_role'] ?? 'غير موجود');
echo "\n";
echo "admin_permissions: ";
print_r($_SESSION['admin_permissions'] ?? 'غير موجود');
echo "\n\n";

echo "=== التحقق من الصلاحيات ===\n";
$test_permissions = [
    'agents.view',
    'agents.create', 
    'agents.edit',
    'agents.delete',
    'products.view',
    'products.create',
    'users.view',
    'users.delete',
];

foreach ($test_permissions as $perm) {
    $has = hasPermission($perm);
    echo "$perm: " . ($has ? '✅ نعم' : '❌ لا') . "\n";
}

echo "\n=== معلومات المسؤول من قاعدة البيانات ===\n";
$admin_id = $_SESSION['admin_id'] ?? 0;
$stmt = $conn->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
print_r($admin);

echo "\n=== معلومات الدور ===\n";
if (isset($admin['role_id']) && $admin['role_id']) {
    $stmt = $conn->prepare("SELECT * FROM admin_roles WHERE id = ?");
    $stmt->bind_param("i", $admin['role_id']);
    $stmt->execute();
    $role = $stmt->get_result()->fetch_assoc();
    print_r($role);
    
    echo "\n=== صلاحيات الدور ===\n";
    $stmt = $conn->prepare("
        SELECT p.permission_key, p.permission_name 
        FROM role_permissions rp 
        JOIN admin_permissions p ON rp.permission_id = p.id 
        WHERE rp.role_id = ?
    ");
    $stmt->bind_param("i", $admin['role_id']);
    $stmt->execute();
    $perms = $stmt->get_result();
    while ($p = $perms->fetch_assoc()) {
        echo "- " . $p['permission_key'] . " (" . $p['permission_name'] . ")\n";
    }
} else {
    echo "لا يوجد role_id للمسؤول!\n";
}

echo "</pre>";

echo "<br><a href='admin_logout.php' style='color:red;'>تسجيل الخروج</a>";
?>

<?php
/**
 * ملف حماية لوحة الإدارة المتقدم
 */

ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تحميل نظام الصلاحيات
$permissions_file = __DIR__ . '/includes/permissions.php';
if (file_exists($permissions_file)) {
    require_once $permissions_file;
}

// تعريف الدوال الأساسية إذا لم تكن موجودة
if (!function_exists('hasPermission')) {
    function hasPermission($permission) { return true; }
}
if (!function_exists('requirePermission')) {
    function requirePermission($permission) { return true; }
}
if (!function_exists('isSuperAdmin')) {
    function isSuperAdmin() { return true; }
}
if (!function_exists('getAdminRole')) {
    function getAdminRole() { return ['id' => 1, 'name' => 'super_admin', 'name_ar' => 'المدير العام']; }
}
if (!function_exists('getAllRoles')) {
    function getAllRoles($conn) { return []; }
}
if (!function_exists('getAllPermissionsGrouped')) {
    function getAllPermissionsGrouped($conn) { return []; }
}
if (!function_exists('getRolePermissions')) {
    function getRolePermissions($conn, $role_id) { return []; }
}
if (!function_exists('updateRolePermissions')) {
    function updateRolePermissions($conn, $role_id, $permissions) { return true; }
}
if (!function_exists('logActivity')) {
    function logActivity($action, $desc, $module = '', $id = null) { return true; }
}
if (!function_exists('getPermissionGroupName')) {
    function getPermissionGroupName($group) { return $group; }
}
if (!function_exists('getPermissionGroupIcon')) {
    function getPermissionGroupIcon($group) { return 'fa-circle'; }
}

define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900);
define('SESSION_TIMEOUT', 1800);

function checkAdminAuth() {
    global $conn;
    
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header("Location: admin_login.php");
        exit();
    }
    
    if (isset($_SESSION['admin_last_activity']) && (time() - $_SESSION['admin_last_activity'] > SESSION_TIMEOUT)) {
        session_unset();
        session_destroy();
        header("Location: admin_login.php?expired=1");
        exit();
    }
    
    // تحميل الصلاحيات دائماً للتأكد من تحديثها
    if (isset($conn) && function_exists('loadAdminPermissions')) {
        loadAdminPermissions($conn, $_SESSION['admin_id']);
    }
    
    $_SESSION['admin_last_activity'] = time();
}

function checkPermission($permission) {
    requirePermission($permission);
}

function getAdminCSRF() {
    if (!isset($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_csrf_token'];
}

function verifyAdminCSRF($token) {
    return isset($_SESSION['admin_csrf_token']) && hash_equals($_SESSION['admin_csrf_token'], $token);
}

function logAdminAction($action, $details = '') {
    global $conn;
    $admin_id = $_SESSION['admin_id'] ?? 0;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
    if ($stmt) {
        $stmt->bind_param("isss", $admin_id, $action, $details, $ip);
        $stmt->execute();
        $stmt->close();
    }
}

function loginAdmin($conn, $username, $password) {
    $stmt = $conn->prepare("SELECT id, name, password, role_id, is_active, login_attempts, locked_until FROM admins WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();
    
    if (!$admin) {
        return ['success' => false, 'message' => 'اسم المستخدم أو كلمة المرور غير صحيحة'];
    }
    
    if (isset($admin['locked_until']) && $admin['locked_until'] && strtotime($admin['locked_until']) > time()) {
        $remaining = ceil((strtotime($admin['locked_until']) - time()) / 60);
        return ['success' => false, 'message' => "الحساب مقفل. حاول بعد $remaining دقيقة"];
    }
    
    if (isset($admin['is_active']) && !$admin['is_active']) {
        return ['success' => false, 'message' => 'هذا الحساب معطل'];
    }
    
    if (!password_verify($password, $admin['password'])) {
        $attempts = ($admin['login_attempts'] ?? 0) + 1;
        
        if ($attempts >= MAX_LOGIN_ATTEMPTS) {
            $locked_until = date('Y-m-d H:i:s', time() + LOCKOUT_TIME);
            $stmt = $conn->prepare("UPDATE admins SET login_attempts = ?, locked_until = ? WHERE id = ?");
            $stmt->bind_param("isi", $attempts, $locked_until, $admin['id']);
            $stmt->execute();
            return ['success' => false, 'message' => 'تم قفل الحساب بسبب المحاولات الفاشلة'];
        }
        
        $stmt = $conn->prepare("UPDATE admins SET login_attempts = ? WHERE id = ?");
        $stmt->bind_param("ii", $attempts, $admin['id']);
        $stmt->execute();
        
        return ['success' => false, 'message' => 'كلمة المرور غير صحيحة'];
    }
    
    // تسجيل دخول ناجح
    $stmt = $conn->prepare("UPDATE admins SET login_attempts = 0, locked_until = NULL, last_login = NOW() WHERE id = ?");
    $stmt->bind_param("i", $admin['id']);
    $stmt->execute();
    
    session_regenerate_id(true);
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_name'] = $admin['name'];
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_last_activity'] = time();
    
    loadAdminPermissions($conn, $admin['id']);
    
    return ['success' => true, 'message' => 'تم تسجيل الدخول بنجاح'];
}
?>

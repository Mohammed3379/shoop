<?php
/**
 * نظام إدارة الصلاحيات المتقدم
 */

if (defined('PERMISSIONS_LOADED')) {
    return;
}
define('PERMISSIONS_LOADED', true);

$ADMIN_PERMISSIONS = [];
$ADMIN_ROLE = null;

/**
 * تحميل صلاحيات المسؤول الحالي
 */
function loadAdminPermissions($conn, $admin_id) {
    global $ADMIN_PERMISSIONS, $ADMIN_ROLE;
    
    // التحقق من وجود جدول الأدوار
    $table_exists = $conn->query("SHOW TABLES LIKE 'admin_roles'");
    if (!$table_exists || $table_exists->num_rows == 0) {
        // الجداول غير موجودة - إعطاء جميع الصلاحيات
        $ADMIN_ROLE = ['id' => 1, 'name' => 'super_admin', 'name_ar' => 'المدير العام'];
        $ADMIN_PERMISSIONS = ['*'];
        $_SESSION['admin_permissions'] = $ADMIN_PERMISSIONS;
        $_SESSION['admin_role'] = $ADMIN_ROLE;
        return;
    }
    
    try {
        $stmt = $conn->prepare("
            SELECT a.role_id, r.role_name, r.role_name_ar 
            FROM admins a 
            LEFT JOIN admin_roles r ON a.role_id = r.id 
            WHERE a.id = ?
        ");
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $admin = $result->fetch_assoc();
        
        if (!$admin || !$admin['role_id']) {
            // المدير الأول (id=1) يحصل على جميع الصلاحيات تلقائياً
            if ($admin_id == 1) {
                $ADMIN_ROLE = ['id' => 1, 'name' => 'super_admin', 'name_ar' => 'المدير العام'];
                $ADMIN_PERMISSIONS = ['*'];
            } else {
                // لا يوجد دور محدد - لا صلاحيات (فقط لوحة التحكم)
                $ADMIN_ROLE = ['id' => 0, 'name' => 'no_role', 'name_ar' => 'بدون دور'];
                $ADMIN_PERMISSIONS = ['dashboard.view'];
            }
        } else {
            $ADMIN_ROLE = [
                'id' => $admin['role_id'],
                'name' => $admin['role_name'],
                'name_ar' => $admin['role_name_ar']
            ];
            
            if ($admin['role_name'] === 'super_admin') {
                $ADMIN_PERMISSIONS = ['*'];
            } else {
                $stmt = $conn->prepare("
                    SELECT p.permission_key 
                    FROM role_permissions rp 
                    JOIN admin_permissions p ON rp.permission_id = p.id 
                    WHERE rp.role_id = ?
                ");
                $stmt->bind_param("i", $admin['role_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $ADMIN_PERMISSIONS = [];
                while ($row = $result->fetch_assoc()) {
                    $ADMIN_PERMISSIONS[] = $row['permission_key'];
                }
            }
        }
    } catch (Exception $e) {
        // خطأ في قاعدة البيانات - لا صلاحيات
        $ADMIN_ROLE = ['id' => 0, 'name' => 'error', 'name_ar' => 'خطأ'];
        $ADMIN_PERMISSIONS = ['dashboard.view'];
    }
    
    $_SESSION['admin_permissions'] = $ADMIN_PERMISSIONS;
    $_SESSION['admin_role'] = $ADMIN_ROLE;
}

/**
 * التحقق من صلاحية معينة
 */
function hasPermission($permission) {
    global $ADMIN_PERMISSIONS;
    
    // تحميل الصلاحيات من الجلسة إذا لم تكن محملة
    if (empty($ADMIN_PERMISSIONS) && isset($_SESSION['admin_permissions'])) {
        $ADMIN_PERMISSIONS = $_SESSION['admin_permissions'];
    }
    
    // المدير العام له جميع الصلاحيات
    if (is_array($ADMIN_PERMISSIONS) && in_array('*', $ADMIN_PERMISSIONS)) {
        return true;
    }
    
    // إذا لم تكن هناك صلاحيات محددة، منع الوصول
    if (empty($ADMIN_PERMISSIONS) || !is_array($ADMIN_PERMISSIONS)) {
        return false;
    }
    
    // التحقق من الصلاحية المحددة
    if (in_array($permission, $ADMIN_PERMISSIONS)) {
        return true;
    }
    
    // التحقق من صلاحية المجموعة (مثل products.*)
    $parts = explode('.', $permission);
    if (count($parts) === 2) {
        if (in_array($parts[0] . '.*', $ADMIN_PERMISSIONS)) {
            return true;
        }
    }
    
    return false;
}

/**
 * التحقق من صلاحية مع إيقاف التنفيذ
 */
function requirePermission($permission, $redirect = true) {
    // السماح دائماً إذا لم تكن الصلاحيات مُعدّة
    if (!hasPermission($permission)) {
        if ($redirect) {
            header('HTTP/1.1 403 Forbidden');
            include __DIR__ . '/access_denied.php';
            exit;
        }
        return false;
    }
    return true;
}

function hasAnyPermission($permissions) {
    foreach ($permissions as $permission) {
        if (hasPermission($permission)) return true;
    }
    return false;
}

function hasAllPermissions($permissions) {
    foreach ($permissions as $permission) {
        if (!hasPermission($permission)) return false;
    }
    return true;
}

function getAdminRole() {
    global $ADMIN_ROLE;
    if (!$ADMIN_ROLE && isset($_SESSION['admin_role'])) {
        $ADMIN_ROLE = $_SESSION['admin_role'];
    }
    return $ADMIN_ROLE;
}

function isSuperAdmin() {
    $role = getAdminRole();
    return $role && $role['name'] === 'super_admin';
}

function getAllRoles($conn) {
    try {
        $result = $conn->query("SELECT * FROM admin_roles WHERE is_active = 1 ORDER BY id");
        if (!$result) return [];
        $roles = [];
        while ($row = $result->fetch_assoc()) {
            $roles[] = $row;
        }
        return $roles;
    } catch (Exception $e) {
        return [];
    }
}

function getAllPermissionsGrouped($conn) {
    try {
        $result = $conn->query("SELECT * FROM admin_permissions ORDER BY permission_group, id");
        if (!$result) return [];
        $permissions = [];
        while ($row = $result->fetch_assoc()) {
            $group = $row['permission_group'];
            if (!isset($permissions[$group])) {
                $permissions[$group] = [];
            }
            $permissions[$group][] = $row;
        }
        return $permissions;
    } catch (Exception $e) {
        return [];
    }
}

function getRolePermissions($conn, $role_id) {
    try {
        $stmt = $conn->prepare("
            SELECT p.permission_key 
            FROM role_permissions rp 
            JOIN admin_permissions p ON rp.permission_id = p.id 
            WHERE rp.role_id = ?
        ");
        $stmt->bind_param("i", $role_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $permissions = [];
        while ($row = $result->fetch_assoc()) {
            $permissions[] = $row['permission_key'];
        }
        return $permissions;
    } catch (Exception $e) {
        return [];
    }
}

function updateRolePermissions($conn, $role_id, $permissions) {
    try {
        $stmt = $conn->prepare("DELETE FROM role_permissions WHERE role_id = ?");
        $stmt->bind_param("i", $role_id);
        $stmt->execute();
        
        $stmt = $conn->prepare("
            INSERT INTO role_permissions (role_id, permission_id) 
            SELECT ?, id FROM admin_permissions WHERE permission_key = ?
        ");
        
        foreach ($permissions as $permission) {
            $stmt->bind_param("is", $role_id, $permission);
            $stmt->execute();
        }
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function logActivity($action_type, $description, $module = '', $record_id = null) {
    global $conn;
    try {
        $admin_id = $_SESSION['admin_id'] ?? 0;
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt = $conn->prepare("
            INSERT INTO admin_activity_logs 
            (admin_id, action_type, action_description, module, record_id, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("isssiss", $admin_id, $action_type, $description, $module, $record_id, $ip, $user_agent);
        $stmt->execute();
    } catch (Exception $e) {
        // تجاهل الأخطاء
    }
}

function logUnauthorizedAccess($permission) {
    logActivity('unauthorized_access', "محاولة وصول غير مصرح: $permission", 'security');
}

function getPermissionGroupName($group) {
    $groups = [
        'dashboard' => 'لوحة التحكم',
        'products' => 'المنتجات',
        'categories' => 'الفئات',
        'orders' => 'الطلبات',
        'users' => 'العملاء',
        'agents' => 'فريق التوصيل',
        'banners' => 'البانرات',
        'settings' => 'الإعدادات',
        'admins' => 'المسؤولين',
        'reports' => 'التقارير',
        'logs' => 'سجل النشاطات'
    ];
    return $groups[$group] ?? $group;
}

function getPermissionGroupIcon($group) {
    $icons = [
        'dashboard' => 'fa-chart-pie',
        'products' => 'fa-boxes',
        'categories' => 'fa-tags',
        'orders' => 'fa-shopping-bag',
        'users' => 'fa-users',
        'agents' => 'fa-motorcycle',
        'banners' => 'fa-images',
        'settings' => 'fa-cog',
        'admins' => 'fa-user-shield',
        'reports' => 'fa-chart-bar',
        'logs' => 'fa-history'
    ];
    return $icons[$group] ?? 'fa-circle';
}
?>

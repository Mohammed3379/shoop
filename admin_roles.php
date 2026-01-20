<?php
/**
 * إدارة الأدوار والصلاحيات
 */
include '../app/config/database.php';
include 'admin_auth.php';

checkAdminAuth();

// التحقق من الصلاحية إذا كانت الدالة موجودة
if (function_exists('requirePermission')) {
    requirePermission('admins.roles');
}

$page_title = 'إدارة الأدوار والصلاحيات';
$page_icon = 'fa-user-tag';
$csrf_token = getAdminCSRF();
$message = '';
$message_type = '';

// معالجة الإجراءات
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyAdminCSRF($_POST['csrf_token'] ?? '')) {
    
    // إضافة دور جديد
    if (isset($_POST['add_role'])) {
        $role_name = preg_replace('/[^a-z0-9_]/', '', strtolower($_POST['role_name']));
        $role_name_ar = trim($_POST['role_name_ar']);
        $description = trim($_POST['description']);
        
        if (empty($role_name) || empty($role_name_ar)) {
            $message = 'يرجى ملء جميع الحقول المطلوبة';
            $message_type = 'danger';
        } else {
            $stmt = $conn->prepare("INSERT INTO admin_roles (role_name, role_name_ar, description) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $role_name, $role_name_ar, $description);
            
            if ($stmt->execute()) {
                $message = 'تم إضافة الدور بنجاح';
                $message_type = 'success';
                logActivity('create', "إضافة دور جديد: $role_name_ar", 'roles', $conn->insert_id);
            } else {
                $message = 'اسم الدور موجود مسبقاً';
                $message_type = 'danger';
            }
        }
    }
    
    // تحديث صلاحيات دور
    if (isset($_POST['update_permissions'])) {
        $role_id = intval($_POST['role_id']);
        $permissions = $_POST['permissions'] ?? [];
        
        // التحقق من أن الدور ليس نظامياً (super_admin)
        $check = $conn->prepare("SELECT role_name FROM admin_roles WHERE id = ?");
        $check->bind_param("i", $role_id);
        $check->execute();
        $role = $check->get_result()->fetch_assoc();
        
        if ($role && $role['role_name'] === 'super_admin') {
            $message = 'لا يمكن تعديل صلاحيات المدير العام';
            $message_type = 'danger';
        } else {
            updateRolePermissions($conn, $role_id, $permissions);
            $message = 'تم تحديث الصلاحيات بنجاح';
            $message_type = 'success';
            logActivity('update', "تحديث صلاحيات الدور", 'roles', $role_id);
        }
    }
    
    // حذف دور
    if (isset($_POST['delete_role'])) {
        $role_id = intval($_POST['role_id']);
        
        // التحقق من أن الدور ليس نظامياً
        $check = $conn->prepare("SELECT is_system FROM admin_roles WHERE id = ?");
        $check->bind_param("i", $role_id);
        $check->execute();
        $role = $check->get_result()->fetch_assoc();
        
        if ($role && $role['is_system']) {
            $message = 'لا يمكن حذف الأدوار النظامية';
            $message_type = 'danger';
        } else {
            // التحقق من عدم وجود مسؤولين بهذا الدور
            $check = $conn->prepare("SELECT COUNT(*) as count FROM admins WHERE role_id = ?");
            $check->bind_param("i", $role_id);
            $check->execute();
            $count = $check->get_result()->fetch_assoc()['count'];
            
            if ($count > 0) {
                $message = "لا يمكن حذف الدور، يوجد $count مسؤول يستخدمه";
                $message_type = 'danger';
            } else {
                $stmt = $conn->prepare("DELETE FROM admin_roles WHERE id = ?");
                $stmt->bind_param("i", $role_id);
                $stmt->execute();
                $message = 'تم حذف الدور بنجاح';
                $message_type = 'success';
            }
        }
    }
}

// جلب الأدوار
$roles = getAllRoles($conn);
if (empty($roles)) {
    $message = 'يرجى تنفيذ ملف permissions_tables.sql أولاً لإنشاء جداول الصلاحيات';
    $message_type = 'warning';
    $roles = [];
}

// جلب الصلاحيات مجمعة
$permissions_grouped = getAllPermissionsGrouped($conn);

// الدور المحدد للتعديل
$selected_role_id = isset($_GET['role']) ? intval($_GET['role']) : ($roles[0]['id'] ?? 0);
$selected_role_permissions = getRolePermissions($conn, $selected_role_id);

include 'includes/admin_header.php';
?>

<style>
/* ========== تصميم عصري وأنيق لصفحة الأدوار ========== */
.roles-container {
    display: grid;
    grid-template-columns: 340px 1fr;
    gap: 30px;
    animation: fadeInUp 0.5s ease;
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

@media (max-width: 992px) {
    .roles-container { grid-template-columns: 1fr; }
    .roles-sidebar { margin-bottom: 20px; }
}

@media (max-width: 768px) {
    .roles-header {
        padding: 18px;
    }
    
    .roles-header h3 {
        font-size: 16px;
    }
    
    .btn-add-role {
        width: 38px;
        height: 38px;
        font-size: 16px;
    }
    
    .roles-list {
        max-height: 350px;
        padding: 8px;
    }
    
    .role-item {
        padding: 14px 16px;
        margin-bottom: 8px;
    }
    
    .role-item .role-info h4 {
        font-size: 14px;
    }
    
    .role-item .role-info p {
        font-size: 11px;
    }
    
    .permissions-header {
        padding: 18px 20px;
        flex-direction: column;
        gap: 10px;
        align-items: flex-start;
    }
    
    .permissions-header h3 {
        font-size: 16px;
    }
    
    .permissions-body {
        padding: 15px;
    }
    
    .permission-group-header {
        padding: 14px 16px;
    }
    
    .permission-group-header i {
        width: 35px;
        height: 35px;
        font-size: 16px;
    }
    
    .permission-group-header h4 {
        font-size: 14px;
    }
    
    .permission-items {
        padding: 15px;
        grid-template-columns: 1fr;
    }
    
    .permission-item {
        padding: 12px 14px;
    }
    
    .permission-item label {
        font-size: 12px;
    }
    
    .btn-save-permissions {
        width: 100%;
        justify-content: center;
        padding: 14px 20px;
        font-size: 14px;
    }
    
    /* Modal Responsive */
    .modal-content {
        max-width: 95%;
        margin: 10px;
    }
    
    .modal-header {
        padding: 18px 20px;
    }
    
    .modal-header h3 {
        font-size: 16px;
    }
    
    .modal-body {
        padding: 20px;
    }
    
    .form-control {
        padding: 12px 14px;
    }
    
    .super-admin-notice {
        padding: 25px;
    }
    
    .super-admin-notice i {
        font-size: 40px;
    }
    
    .super-admin-notice h4 {
        font-size: 18px;
    }
}

@media (max-width: 480px) {
    .role-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .permission-group-header .toggle-all {
        font-size: 11px;
        padding: 5px 10px;
    }
}

/* ========== الشريط الجانبي للأدوار ========== */
.roles-sidebar {
    background: linear-gradient(145deg, var(--bg-card), rgba(99, 102, 241, 0.03));
    border-radius: 20px;
    border: 1px solid rgba(99, 102, 241, 0.15);
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
}

.roles-header {
    padding: 25px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.05));
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.roles-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.btn-add-role {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border: none;
    width: 42px;
    height: 42px;
    border-radius: 12px;
    cursor: pointer;
    font-size: 18px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
}

.btn-add-role:hover {
    transform: translateY(-2px) scale(1.05);
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.5);
}

.roles-list {
    max-height: 550px;
    overflow-y: auto;
    padding: 10px;
}

.roles-list::-webkit-scrollbar { width: 6px; }
.roles-list::-webkit-scrollbar-track { background: transparent; }
.roles-list::-webkit-scrollbar-thumb { background: rgba(99, 102, 241, 0.3); border-radius: 10px; }

.role-item {
    padding: 18px 20px;
    margin-bottom: 10px;
    border-radius: 14px;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--bg-card);
    border: 1px solid transparent;
    text-decoration: none;
    color: inherit;
    position: relative;
    overflow: hidden;
}

.role-item::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(180deg, #6366f1, #8b5cf6);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.role-item:hover {
    background: rgba(99, 102, 241, 0.08);
    border-color: rgba(99, 102, 241, 0.2);
    transform: translateX(-5px);
}

.role-item:hover::before { opacity: 1; }

.role-item.active {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(139, 92, 246, 0.1));
    border-color: rgba(99, 102, 241, 0.3);
    box-shadow: 0 4px 20px rgba(99, 102, 241, 0.2);
}

.role-item.active::before { opacity: 1; }

.role-item .role-info h4 {
    margin: 0 0 6px 0;
    font-size: 15px;
    font-weight: 600;
    color: var(--text-primary);
}

.role-item .role-info p {
    margin: 0;
    font-size: 12px;
    color: var(--text-muted);
    line-height: 1.4;
}

.role-item .role-badge {
    font-size: 10px;
    padding: 5px 12px;
    border-radius: 20px;
    background: rgba(100, 116, 139, 0.1);
    color: var(--text-muted);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.role-item .role-badge.system {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(139, 92, 246, 0.15));
    color: #8b5cf6;
    box-shadow: 0 2px 10px rgba(139, 92, 246, 0.2);
}

/* ========== لوحة الصلاحيات ========== */
.permissions-panel {
    background: linear-gradient(145deg, var(--bg-card), rgba(99, 102, 241, 0.02));
    border-radius: 20px;
    border: 1px solid rgba(99, 102, 241, 0.1);
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
    overflow: hidden;
}

.permissions-header {
    padding: 25px 30px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.08), rgba(139, 92, 246, 0.03));
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.permissions-header h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 12px;
}

.permissions-header h3 i {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.permissions-body {
    padding: 30px;
}

/* ========== مجموعات الصلاحيات ========== */
.permission-group {
    margin-bottom: 20px;
    background: linear-gradient(145deg, rgba(99, 102, 241, 0.03), transparent);
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid rgba(99, 102, 241, 0.08);
    transition: all 0.3s ease;
}

.permission-group:hover {
    border-color: rgba(99, 102, 241, 0.15);
    box-shadow: 0 4px 20px rgba(99, 102, 241, 0.1);
}

.permission-group-header {
    padding: 18px 22px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.06), rgba(139, 92, 246, 0.02));
    display: flex;
    align-items: center;
    gap: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.permission-group-header:hover {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.05));
}

.permission-group-header i {
    font-size: 20px;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(139, 92, 246, 0.1));
    border-radius: 10px;
    color: #8b5cf6;
}

.permission-group-header h4 {
    margin: 0;
    font-size: 15px;
    font-weight: 600;
    flex: 1;
}

.permission-group-header .toggle-all {
    font-size: 12px;
    color: #8b5cf6;
    cursor: pointer;
    padding: 6px 14px;
    background: rgba(139, 92, 246, 0.1);
    border-radius: 20px;
    transition: all 0.3s ease;
    font-weight: 500;
}

.permission-group-header .toggle-all:hover {
    background: rgba(139, 92, 246, 0.2);
    transform: scale(1.05);
}

.permission-items {
    padding: 20px;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 12px;
    background: rgba(0, 0, 0, 0.02);
}

.permission-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    background: var(--bg-card);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 1px solid transparent;
}

.permission-item:hover {
    background: rgba(99, 102, 241, 0.08);
    border-color: rgba(99, 102, 241, 0.15);
    transform: translateY(-2px);
}

.permission-item input[type="checkbox"] {
    width: 20px;
    height: 20px;
    accent-color: #8b5cf6;
    cursor: pointer;
}

.permission-item label {
    cursor: pointer;
    font-size: 13px;
    flex: 1;
    font-weight: 500;
    color: var(--text-primary);
}

/* ========== زر الحفظ ========== */
.btn-save-permissions {
    background: linear-gradient(135deg, #6366f1, #8b5cf6, #a855f7);
    background-size: 200% 200%;
    color: white;
    border: none;
    padding: 16px 35px;
    border-radius: 14px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 12px;
    box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
    transition: all 0.4s ease;
    animation: gradientShift 3s ease infinite;
}

@keyframes gradientShift {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

.btn-save-permissions:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 35px rgba(99, 102, 241, 0.5);
}

.btn-save-permissions i {
    font-size: 18px;
}

/* ========== Modal عصري ========== */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(8px);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal.active {
    display: flex;
    animation: modalFadeIn 0.3s ease;
}

@keyframes modalFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-content {
    background: linear-gradient(145deg, var(--bg-card), rgba(99, 102, 241, 0.03));
    border-radius: 24px;
    width: 100%;
    max-width: 480px;
    border: 1px solid rgba(99, 102, 241, 0.2);
    box-shadow: 0 25px 80px rgba(0, 0, 0, 0.4);
    animation: modalSlideIn 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes modalSlideIn {
    from { opacity: 0; transform: scale(0.9) translateY(-20px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

.modal-header {
    padding: 25px 30px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.05));
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 24px 24px 0 0;
}

.modal-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-header h3 i {
    color: #8b5cf6;
}

.modal-close {
    background: rgba(239, 68, 68, 0.1);
    border: none;
    font-size: 20px;
    color: #ef4444;
    cursor: pointer;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    transition: all 0.3s ease;
}

.modal-close:hover {
    background: #ef4444;
    color: white;
    transform: rotate(90deg);
}

.modal-body {
    padding: 30px;
}

.form-group {
    margin-bottom: 22px;
}

.form-group label {
    display: block;
    margin-bottom: 10px;
    font-weight: 600;
    font-size: 14px;
    color: var(--text-primary);
}

.form-control {
    width: 100%;
    padding: 14px 18px;
    background: rgba(99, 102, 241, 0.05);
    border: 2px solid rgba(99, 102, 241, 0.1);
    border-radius: 12px;
    color: var(--text-primary);
    font-size: 14px;
    transition: all 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: #8b5cf6;
    background: rgba(99, 102, 241, 0.08);
    box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.15);
}

.form-control::placeholder {
    color: var(--text-muted);
}

.form-group small {
    display: block;
    margin-top: 8px;
    font-size: 12px;
    color: var(--text-muted);
}

.btn-submit {
    width: 100%;
    padding: 16px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
    transition: all 0.3s ease;
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(99, 102, 241, 0.5);
}

/* ========== إشعار المدير العام ========== */
.super-admin-notice {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(251, 191, 36, 0.05));
    border: 2px solid rgba(245, 158, 11, 0.3);
    border-radius: 20px;
    padding: 40px;
    text-align: center;
    animation: pulse 2s ease infinite;
}

@keyframes pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.2); }
    50% { box-shadow: 0 0 0 15px rgba(245, 158, 11, 0); }
}

.super-admin-notice i {
    font-size: 50px;
    margin-bottom: 15px;
    display: block;
    background: linear-gradient(135deg, #f59e0b, #fbbf24);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.super-admin-notice h4 {
    margin: 0 0 10px 0;
    font-size: 22px;
    color: #f59e0b;
}

.super-admin-notice p {
    margin: 0;
    color: var(--text-muted);
    font-size: 14px;
}

/* ========== تنبيهات ========== */
.alert {
    padding: 18px 24px;
    border-radius: 14px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 14px;
    font-weight: 500;
    animation: slideIn 0.4s ease;
}

@keyframes slideIn {
    from { opacity: 0; transform: translateX(-20px); }
    to { opacity: 1; transform: translateX(0); }
}

.alert-success {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(52, 211, 153, 0.1));
    border: 1px solid rgba(16, 185, 129, 0.3);
    color: #10b981;
}

.alert-danger {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(248, 113, 113, 0.1));
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

.alert-warning {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(251, 191, 36, 0.1));
    border: 1px solid rgba(245, 158, 11, 0.3);
    color: #f59e0b;
}

.alert i {
    font-size: 20px;
}
</style>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?>">
    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
    <?php echo $message; ?>
</div>
<?php endif; ?>

<div class="roles-container">
    <!-- قائمة الأدوار -->
    <div class="roles-sidebar">
        <div class="roles-header">
            <h3><i class="fas fa-user-tag"></i> الأدوار</h3>
            <button class="btn-add-role" onclick="openModal('addRoleModal')" title="إضافة دور">
                <i class="fas fa-plus"></i>
            </button>
        </div>
        <div class="roles-list">
            <?php foreach ($roles as $role): ?>
            <a href="?role=<?php echo $role['id']; ?>" class="role-item <?php echo $selected_role_id == $role['id'] ? 'active' : ''; ?>">
                <div class="role-info">
                    <h4><?php echo htmlspecialchars($role['role_name_ar']); ?></h4>
                    <p><?php echo htmlspecialchars($role['description'] ?? ''); ?></p>
                </div>
                <?php if ($role['is_system']): ?>
                <span class="role-badge system">نظامي</span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- لوحة الصلاحيات -->
    <div class="permissions-panel">
        <div class="permissions-header">
            <h3>
                <i class="fas fa-key"></i>
                صلاحيات الدور: 
                <?php 
                foreach ($roles as $r) {
                    if ($r['id'] == $selected_role_id) {
                        echo htmlspecialchars($r['role_name_ar']);
                        $is_super = ($r['role_name'] === 'super_admin');
                        break;
                    }
                }
                ?>
            </h3>
        </div>
        <div class="permissions-body">
            <?php if (isset($is_super) && $is_super): ?>
            <div class="super-admin-notice">
                <i class="fas fa-crown"></i>
                <h4>المدير العام</h4>
                <p>هذا الدور يمتلك جميع الصلاحيات تلقائياً ولا يمكن تعديله</p>
            </div>
            <?php else: ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="role_id" value="<?php echo $selected_role_id; ?>">
                
                <?php foreach ($permissions_grouped as $group => $perms): ?>
                <div class="permission-group">
                    <div class="permission-group-header" onclick="toggleGroup(this)">
                        <i class="fas <?php echo getPermissionGroupIcon($group); ?>"></i>
                        <h4><?php echo getPermissionGroupName($group); ?></h4>
                        <span class="toggle-all" onclick="event.stopPropagation(); toggleAllInGroup('<?php echo $group; ?>')">تحديد الكل</span>
                    </div>
                    <div class="permission-items">
                        <?php foreach ($perms as $perm): ?>
                        <div class="permission-item">
                            <input type="checkbox" 
                                   name="permissions[]" 
                                   value="<?php echo $perm['permission_key']; ?>"
                                   id="perm_<?php echo $perm['id']; ?>"
                                   data-group="<?php echo $group; ?>"
                                   <?php echo in_array($perm['permission_key'], $selected_role_permissions) ? 'checked' : ''; ?>>
                            <label for="perm_<?php echo $perm['id']; ?>"><?php echo $perm['permission_name']; ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <button type="submit" name="update_permissions" class="btn-save-permissions">
                    <i class="fas fa-save"></i>
                    حفظ الصلاحيات
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal إضافة دور -->
<div class="modal" id="addRoleModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle"></i> إضافة دور جديد</h3>
            <button class="modal-close" onclick="closeModal('addRoleModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-group">
                    <label>اسم الدور (بالإنجليزية)</label>
                    <input type="text" name="role_name" class="form-control" placeholder="مثال: content_manager" required pattern="[a-z0-9_]+">
                    <small style="color: var(--text-muted);">أحرف صغيرة وأرقام وشرطة سفلية فقط</small>
                </div>
                
                <div class="form-group">
                    <label>اسم الدور (بالعربية)</label>
                    <input type="text" name="role_name_ar" class="form-control" placeholder="مثال: مدير المحتوى" required>
                </div>
                
                <div class="form-group">
                    <label>الوصف</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="وصف مختصر للدور وصلاحياته"></textarea>
                </div>
                
                <button type="submit" name="add_role" class="btn-submit">
                    <i class="fas fa-plus"></i> إضافة الدور
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function openModal(id) {
    document.getElementById(id).classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

function toggleGroup(header) {
    const items = header.nextElementSibling;
    items.style.display = items.style.display === 'none' ? 'grid' : 'none';
}

function toggleAllInGroup(group) {
    const checkboxes = document.querySelectorAll(`input[data-group="${group}"]`);
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    checkboxes.forEach(cb => cb.checked = !allChecked);
}

document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});
</script>

<?php include 'includes/admin_footer.php'; ?>

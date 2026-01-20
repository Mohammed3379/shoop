<?php
/**
 * إدارة المسؤولين
 */
include '../app/config/database.php';
include 'admin_auth.php';

checkAdminAuth();

// التحقق من الصلاحية إذا كانت الدالة موجودة
if (function_exists('requirePermission')) {
    requirePermission('admins.view');
}

$page_title = 'إدارة المسؤولين';
$page_icon = 'fa-user-shield';
$csrf_token = getAdminCSRF();
$message = '';
$message_type = '';

// معالجة الإجراءات
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyAdminCSRF($_POST['csrf_token'] ?? '')) {
    
    // إضافة مسؤول جديد
    if (isset($_POST['add_admin']) && hasPermission('admins.create')) {
        $name = trim($_POST['name']);
        $username = trim($_POST['username']);
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'];
        $role_id = intval($_POST['role_id']);
        
        if (empty($username) || empty($password)) {
            $message = 'اسم المستخدم وكلمة المرور مطلوبة';
            $message_type = 'danger';
        } else {
            // التحقق من عدم تكرار اسم المستخدم
            $check = $conn->prepare("SELECT id FROM admins WHERE username = ?");
            $check->bind_param("s", $username);
            $check->execute();
            
            if ($check->get_result()->num_rows > 0) {
                $message = 'اسم المستخدم موجود مسبقاً';
                $message_type = 'danger';
            } else {
                // التحقق من عدم تكرار الإيميل إذا تم إدخاله
                $email_exists = false;
                if (!empty($email)) {
                    $check_email = $conn->prepare("SELECT id FROM admins WHERE email = ?");
                    $check_email->bind_param("s", $email);
                    $check_email->execute();
                    $email_exists = $check_email->get_result()->num_rows > 0;
                }
                
                if ($email_exists) {
                    $message = 'البريد الإلكتروني موجود مسبقاً';
                    $message_type = 'danger';
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // إنشاء إيميل فريد إذا لم يتم إدخاله
                    if (empty($email)) {
                        $email = $username . '_' . time() . '@admin.local';
                    }
                    
                    // التحقق من وجود عمود role_id في الجدول
                    $has_role_id = false;
                    $columns = $conn->query("SHOW COLUMNS FROM admins LIKE 'role_id'");
                    if ($columns && $columns->num_rows > 0) {
                        $has_role_id = true;
                    }
                    
                    // إضافة مع الإيميل والدور
                    if ($has_role_id) {
                        $stmt = $conn->prepare("INSERT INTO admins (username, password, email, role_id) VALUES (?, ?, ?, ?)");
                        $stmt->bind_param("sssi", $username, $hashed_password, $email, $role_id);
                    } else {
                        $stmt = $conn->prepare("INSERT INTO admins (username, password, email) VALUES (?, ?, ?)");
                        $stmt->bind_param("sss", $username, $hashed_password, $email);
                    }
                    
                    if ($stmt->execute()) {
                        $message = 'تم إضافة المسؤول بنجاح';
                        $message_type = 'success';
                        logActivity('create', "إضافة مسؤول جديد: $username", 'admins', $conn->insert_id);
                    } else {
                        $message = 'حدث خطأ أثناء الإضافة: ' . $conn->error;
                        $message_type = 'danger';
                    }
                }
            }
        }
    }
    
    // تعديل مسؤول
    if (isset($_POST['edit_admin']) && hasPermission('admins.edit')) {
        $admin_id = intval($_POST['admin_id']);
        $role_id = intval($_POST['role_id'] ?? 1);
        
        // منع تعديل المدير العام الأول
        if ($admin_id === 1 && !isSuperAdmin()) {
            $message = 'لا يمكن تعديل المدير العام';
            $message_type = 'danger';
        } else {
            // التحقق من وجود عمود role_id في الجدول
            $has_role_id = false;
            $columns = $conn->query("SHOW COLUMNS FROM admins LIKE 'role_id'");
            if ($columns && $columns->num_rows > 0) {
                $has_role_id = true;
            }
            
            $updated = false;
            
            // تحديث الدور إذا كان العمود موجوداً
            if ($has_role_id) {
                $stmt = $conn->prepare("UPDATE admins SET role_id = ? WHERE id = ?");
                $stmt->bind_param("ii", $role_id, $admin_id);
                $updated = $stmt->execute();
            } else {
                $updated = true;
            }
            
            // تحديث كلمة المرور إذا تم إدخالها
            if ($updated && !empty($_POST['new_password'])) {
                $hashed = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashed, $admin_id);
                $updated = $stmt->execute();
            }
            
            if ($updated) {
                $message = 'تم تحديث بيانات المسؤول';
                $message_type = 'success';
                logActivity('update', "تعديل مسؤول", 'admins', $admin_id);
            }
        }
    }
    
    // حذف مسؤول
    if (isset($_POST['delete_admin']) && hasPermission('admins.delete')) {
        $admin_id = intval($_POST['admin_id']);
        
        // منع حذف المدير العام أو النفس
        if ($admin_id === 1) {
            $message = 'لا يمكن حذف المدير العام';
            $message_type = 'danger';
        } elseif ($admin_id === $_SESSION['admin_id']) {
            $message = 'لا يمكنك حذف حسابك';
            $message_type = 'danger';
        } else {
            $stmt = $conn->prepare("DELETE FROM admins WHERE id = ?");
            $stmt->bind_param("i", $admin_id);
            
            if ($stmt->execute()) {
                $message = 'تم حذف المسؤول';
                $message_type = 'success';
                logActivity('delete', "حذف مسؤول", 'admins', $admin_id);
            }
        }
    }
}

// جلب المسؤولين
try {
    // التحقق من وجود جدول الأدوار
    $table_check = $conn->query("SHOW TABLES LIKE 'admin_roles'");
    if ($table_check && $table_check->num_rows > 0) {
        $admins = $conn->query("
            SELECT a.*, r.role_name_ar 
            FROM admins a 
            LEFT JOIN admin_roles r ON a.role_id = r.id 
            ORDER BY a.id
        ");
    } else {
        $admins = $conn->query("SELECT *, 'مدير عام' as role_name_ar FROM admins ORDER BY id");
    }
} catch (Exception $e) {
    $admins = $conn->query("SELECT *, 'مدير عام' as role_name_ar FROM admins ORDER BY id");
}

// جلب الأدوار
$roles = getAllRoles($conn);
if (empty($roles)) {
    $roles = [['id' => 1, 'role_name_ar' => 'مدير عام']];
}

include 'includes/admin_header.php';
?>

<style>
.admins-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.admin-card {
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-color);
    padding: 25px;
    position: relative;
    transition: all 0.3s;
}

.admin-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

.admin-card.inactive {
    opacity: 0.6;
}

.admin-avatar {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    font-weight: bold;
    color: white;
    margin: 0 auto 15px;
}

.admin-name {
    text-align: center;
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 5px;
}

.admin-role {
    text-align: center;
    color: var(--text-muted);
    font-size: 14px;
    margin-bottom: 15px;
}

.admin-role span {
    background: var(--bg-hover);
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
}

.admin-info {
    border-top: 1px solid var(--border-color);
    padding-top: 15px;
    font-size: 13px;
    color: var(--text-secondary);
}

.admin-info div {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
}

.admin-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.admin-actions button {
    flex: 1;
    padding: 10px;
    border: none;
    border-radius: var(--radius-md);
    cursor: pointer;
    font-size: 13px;
    transition: all 0.2s;
}

.btn-edit {
    background: rgba(99, 102, 241, 0.1);
    color: var(--primary);
}

.btn-edit:hover {
    background: var(--primary);
    color: white;
}

.btn-delete {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.btn-delete:hover {
    background: #ef4444;
    color: white;
}

.status-badge {
    position: absolute;
    top: 15px;
    left: 15px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #10b981;
}

.status-badge.inactive {
    background: #ef4444;
}

.add-admin-card {
    background: var(--bg-hover);
    border: 2px dashed var(--border-color);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    min-height: 250px;
}

.add-admin-card:hover {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.05);
}

.add-admin-card i {
    font-size: 40px;
    color: var(--text-muted);
    margin-bottom: 10px;
}

.add-admin-card span {
    color: var(--text-muted);
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.7);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    width: 100%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    padding: 20px 25px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    font-size: 18px;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    color: var(--text-muted);
    cursor: pointer;
}

.modal-body {
    padding: 25px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
}

.form-control {
    width: 100%;
    padding: 12px 15px;
    background: var(--bg-input);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    color: var(--text-primary);
    font-size: 14px;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
}

.btn-submit {
    width: 100%;
    padding: 14px;
    background: linear-gradient(135deg, var(--primary), #4f46e5);
    color: white;
    border: none;
    border-radius: var(--radius-md);
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
}

.btn-submit:hover {
    opacity: 0.9;
}

.switch-container {
    display: flex;
    align-items: center;
    gap: 12px;
}

.switch {
    position: relative;
    width: 50px;
    height: 26px;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: var(--border-color);
    transition: 0.3s;
    border-radius: 26px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
}

input:checked + .slider {
    background-color: var(--primary);
}

input:checked + .slider:before {
    transform: translateX(24px);
}

/* تحسينات التجاوب */
@media (max-width: 768px) {
    .admins-grid {
        grid-template-columns: 1fr;
    }
    
    .admin-card {
        padding: 20px;
    }
    
    .admin-avatar {
        width: 60px;
        height: 60px;
        font-size: 24px;
    }
    
    .modal-content {
        margin: 15px;
        max-width: calc(100% - 30px);
    }
}

@media (max-width: 576px) {
    .admin-actions {
        flex-direction: column;
    }
    
    .admin-actions button {
        width: 100%;
    }
    
    .admin-info div {
        flex-direction: column;
        gap: 5px;
        text-align: center;
    }
}
</style>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?>">
    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
    <?php echo $message; ?>
</div>
<?php endif; ?>

<div class="admins-grid">
    <?php if (hasPermission('admins.create')): ?>
    <div class="admin-card add-admin-card" onclick="openModal('addModal')">
        <i class="fas fa-plus-circle"></i>
        <span>إضافة مسؤول جديد</span>
    </div>
    <?php endif; ?>
    
    <?php while ($admin = $admins->fetch_assoc()): 
        $admin_name = $admin['name'] ?? $admin['username'] ?? 'مسؤول';
        $admin_username = $admin['username'] ?? '';
        $admin_last_login = $admin['last_login'] ?? null;
    ?>
    <div class="admin-card <?php echo ($admin['is_active'] ?? 1) ? '' : 'inactive'; ?>">
        <div class="status-badge <?php echo ($admin['is_active'] ?? 1) ? '' : 'inactive'; ?>"></div>
        
        <div class="admin-avatar">
            <?php echo mb_substr($admin_name, 0, 1); ?>
        </div>
        
        <div class="admin-name"><?php echo htmlspecialchars($admin_name); ?></div>
        <div class="admin-role">
            <span><?php echo $admin['role_name_ar'] ?? 'مدير عام'; ?></span>
        </div>
        
        <div class="admin-info">
            <div>
                <span>اسم المستخدم:</span>
                <strong><?php echo htmlspecialchars($admin_username); ?></strong>
            </div>
            <div>
                <span>آخر دخول:</span>
                <strong><?php echo $admin_last_login ? date('Y/m/d H:i', strtotime($admin_last_login)) : 'لم يسجل'; ?></strong>
            </div>
        </div>
        
        <?php if (($admin['id'] ?? 0) != 1 || isSuperAdmin()): ?>
        <div class="admin-actions">
            <?php if (hasPermission('admins.edit')): ?>
            <button class="btn-edit" onclick="editAdmin(<?php echo htmlspecialchars(json_encode($admin)); ?>)">
                <i class="fas fa-edit"></i> تعديل
            </button>
            <?php endif; ?>
            
            <?php if (hasPermission('admins.delete') && ($admin['id'] ?? 0) != $_SESSION['admin_id'] && ($admin['id'] ?? 0) != 1): ?>
            <button class="btn-delete" onclick="deleteAdmin(<?php echo $admin['id'] ?? 0; ?>, '<?php echo htmlspecialchars($admin_name); ?>')">
                <i class="fas fa-trash"></i> حذف
            </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endwhile; ?>
</div>

<!-- Modal إضافة مسؤول -->
<div class="modal" id="addModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus"></i> إضافة مسؤول جديد</h3>
            <button class="modal-close" onclick="closeModal('addModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-group">
                    <label>الاسم الكامل</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>اسم المستخدم</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>البريد الإلكتروني <small>(اختياري)</small></label>
                    <input type="email" name="email" class="form-control" placeholder="example@domain.com">
                </div>
                
                <div class="form-group">
                    <label>كلمة المرور</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>الدور</label>
                    <select name="role_id" class="form-control" required>
                        <?php foreach ($roles as $role): ?>
                        <option value="<?php echo $role['id']; ?>"><?php echo $role['role_name_ar']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" name="add_admin" class="btn-submit">
                    <i class="fas fa-plus"></i> إضافة المسؤول
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Modal تعديل مسؤول -->
<div class="modal" id="editModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> تعديل المسؤول</h3>
            <button class="modal-close" onclick="closeModal('editModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="admin_id" id="edit_admin_id">
                
                <div class="form-group">
                    <label>الاسم الكامل</label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>كلمة مرور جديدة <small>(اتركها فارغة للإبقاء)</small></label>
                    <input type="password" name="new_password" class="form-control">
                </div>
                
                <div class="form-group">
                    <label>الدور</label>
                    <select name="role_id" id="edit_role_id" class="form-control" required>
                        <?php foreach ($roles as $role): ?>
                        <option value="<?php echo $role['id']; ?>"><?php echo $role['role_name_ar']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <div class="switch-container">
                        <label class="switch">
                            <input type="checkbox" name="is_active" id="edit_is_active">
                            <span class="slider"></span>
                        </label>
                        <span>الحساب نشط</span>
                    </div>
                </div>
                
                <button type="submit" name="edit_admin" class="btn-submit">
                    <i class="fas fa-save"></i> حفظ التغييرات
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Modal حذف -->
<div class="modal" id="deleteModal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h3 style="color: #ef4444;"><i class="fas fa-trash"></i> تأكيد الحذف</h3>
            <button class="modal-close" onclick="closeModal('deleteModal')">&times;</button>
        </div>
        <div class="modal-body" style="text-align: center;">
            <p>هل أنت متأكد من حذف المسؤول:</p>
            <p><strong id="delete_name"></strong></p>
            <form method="POST" style="margin-top: 20px;">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="admin_id" id="delete_admin_id">
                <button type="button" onclick="closeModal('deleteModal')" style="padding: 12px 25px; background: var(--bg-hover); border: none; border-radius: var(--radius-md); cursor: pointer; margin-left: 10px;">إلغاء</button>
                <button type="submit" name="delete_admin" style="padding: 12px 25px; background: #ef4444; color: white; border: none; border-radius: var(--radius-md); cursor: pointer;">حذف</button>
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

function editAdmin(admin) {
    document.getElementById('edit_admin_id').value = admin.id;
    document.getElementById('edit_name').value = admin.name;
    document.getElementById('edit_role_id').value = admin.role_id || 1;
    document.getElementById('edit_is_active').checked = admin.is_active != 0;
    openModal('editModal');
}

function deleteAdmin(id, name) {
    document.getElementById('delete_admin_id').value = id;
    document.getElementById('delete_name').textContent = name;
    openModal('deleteModal');
}

// إغلاق Modal عند النقر خارجها
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});
</script>

<?php include 'includes/admin_footer.php'; ?>

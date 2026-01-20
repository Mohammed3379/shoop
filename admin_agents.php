<?php
include '../app/config/database.php';
include 'admin_auth.php';

checkAdminAuth();

// التحقق من صلاحية عرض فريق التوصيل
requirePermission('agents.view');

$page_title = 'فريق التوصيل';
$page_icon = 'fa-motorcycle';

$csrf_token = getAdminCSRF();
$message = '';
$message_type = '';

// صلاحيات المستخدم الحالي
$can_create = hasPermission('agents.create');
$can_edit = hasPermission('agents.edit');
$can_delete = hasPermission('agents.delete');

// إضافة سائق جديد
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_agent'])) {
    if (!$can_create) {
        $message = 'ليس لديك صلاحية إضافة سائقين';
        $message_type = 'danger';
    } elseif (!isset($_POST['csrf_token']) || !verifyAdminCSRF($_POST['csrf_token'])) {
        $message = 'طلب غير صالح!';
        $message_type = 'danger';
    } else {
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $password = $_POST['password'];
        $g_name = trim($_POST['guarantor_name']);
        
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        $check_stmt = $conn->prepare("SELECT id FROM delivery_agents WHERE phone = ?");
        $check_stmt->bind_param("s", $phone);
        $check_stmt->execute();
        $check = $check_stmt->get_result();

        if ($check->num_rows > 0) {
            $message = 'رقم الهاتف مسجل لسائق آخر!';
            $message_type = 'danger';
        } else {
            // رفع الصور
            function uploadFile($fileInputName) {
                if (empty($_FILES[$fileInputName]['name'])) return "";
                $target_dir = "../public/uploads/";
                if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
                $file_ext = pathinfo($_FILES[$fileInputName]["name"], PATHINFO_EXTENSION);
                $new_name = uniqid() . "." . $file_ext;
                $target_file = $target_dir . $new_name;
                if (move_uploaded_file($_FILES[$fileInputName]["tmp_name"], $target_file)) {
                    return $target_file;
                }
                return "";
            }

            $driver_img = uploadFile('driver_id_img');
            $guarantee_img = uploadFile('guarantee_img');
            $guarantor_img = uploadFile('guarantor_id_img');

            $stmt = $conn->prepare("INSERT INTO delivery_agents (name, phone, password, driver_id_img, guarantee_img, guarantor_name, guarantor_id_img, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
            $stmt->bind_param("sssssss", $name, $phone, $hashed_password, $driver_img, $guarantee_img, $g_name, $guarantor_img);
            
            if ($stmt->execute()) {
                $message = 'تم إضافة السائق بنجاح!';
                $message_type = 'success';
                logAdminAction('add_agent', "تم إضافة السائق: $name");
            } else {
                $message = 'حدث خطأ في إضافة السائق';
                $message_type = 'danger';
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

// حذف سائق
if (isset($_GET['delete']) && isset($_GET['token'])) {
    if (!$can_delete) {
        $message = 'ليس لديك صلاحية حذف السائقين';
        $message_type = 'danger';
    } elseif (!verifyAdminCSRF($_GET['token'])) {
        $message = 'طلب غير صالح!';
        $message_type = 'danger';
    } else {
        $id = intval($_GET['delete']);
        $stmt = $conn->prepare("DELETE FROM delivery_agents WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        
        logAdminAction('delete_agent', "تم حذف السائق رقم: $id");
        $message = 'تم حذف السائق بنجاح!';
        $message_type = 'success';
    }
}

// إحصائيات
$total_agents = $conn->query("SELECT COUNT(*) as count FROM delivery_agents")->fetch_assoc()['count'] ?? 0;
$active_agents = $conn->query("SELECT COUNT(*) as count FROM delivery_agents WHERE status = 'active'")->fetch_assoc()['count'] ?? 0;

include 'includes/admin_header.php';
?>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?>">
    <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
    <?php echo $message; ?>
</div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: <?php echo $can_create ? '1fr 2fr' : '1fr'; ?>; gap: 25px;">
    
    <?php if ($can_create): ?>
    <!-- نموذج الإضافة -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-user-plus"></i>
                إضافة سائق جديد
            </h3>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-group">
                    <label class="form-label">اسم السائق</label>
                    <input type="text" name="name" class="form-control" required placeholder="الاسم الرباعي">
                </div>
                
                <div class="form-group">
                    <label class="form-label">رقم الهاتف</label>
                    <input type="tel" name="phone" class="form-control" required placeholder="05xxxxxxxx">
                </div>
                
                <div class="form-group">
                    <label class="form-label">كلمة المرور</label>
                    <input type="text" name="password" class="form-control" value="123456" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">اسم الضامن</label>
                    <input type="text" name="guarantor_name" class="form-control" placeholder="اسم الضامن التجاري">
                </div>
                
                <div style="background: var(--bg-hover); padding: 15px; border-radius: var(--radius-md); margin-bottom: 20px;">
                    <h4 style="color: var(--text-primary); font-size: 14px; margin-bottom: 15px;">
                        <i class="fas fa-file-image"></i>
                        الوثائق (اختياري)
                    </h4>
                    
                    <div class="form-group" style="margin-bottom: 10px;">
                        <label class="form-label" style="font-size: 12px;">بطاقة السائق</label>
                        <input type="file" name="driver_id_img" class="form-control" accept="image/*">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 10px;">
                        <label class="form-label" style="font-size: 12px;">بطاقة الضامن</label>
                        <input type="file" name="guarantor_id_img" class="form-control" accept="image/*">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-size: 12px;">ورقة الضمانة</label>
                        <input type="file" name="guarantee_img" class="form-control" accept="image/*">
                    </div>
                </div>
                
                <button type="submit" name="add_agent" class="btn btn-success" style="width: 100%;">
                    <i class="fas fa-plus"></i>
                    إضافة السائق
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- قائمة السائقين -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list"></i>
                فريق التوصيل
                <span class="badge badge-primary" style="margin-right: 10px;"><?php echo $total_agents; ?></span>
            </h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>السائق</th>
                            <th>الهاتف</th>
                            <th>الضامن</th>
                            <th>الحالة</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $result = $conn->query("SELECT * FROM delivery_agents ORDER BY id DESC");
                        
                        if ($result && $result->num_rows > 0):
                            while ($row = $result->fetch_assoc()):
                                $status = $row['status'] ?? 'active';
                        ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div style="width: 42px; height: 42px; background: linear-gradient(135deg, var(--primary), var(--primary-light)); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700;">
                                        <?php echo mb_substr($row['name'], 0, 1); ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600; color: var(--text-primary);">
                                            <?php echo htmlspecialchars($row['name']); ?>
                                        </div>
                                        <div style="font-size: 11px; color: var(--text-muted);">
                                            🔑 <?php echo $row['password']; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <a href="tel:<?php echo $row['phone']; ?>" style="color: var(--info);">
                                    <?php echo $row['phone']; ?>
                                </a>
                            </td>
                            <td style="color: var(--text-secondary);">
                                <?php echo htmlspecialchars($row['guarantor_name'] ?? '-'); ?>
                            </td>
                            <td>
                                <?php if ($status == 'active'): ?>
                                    <span class="badge badge-success">نشط</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">محظور</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <a href="admin_agent_details.php?id=<?php echo $row['id']; ?>" 
                                       class="action-btn view" data-tooltip="الملف الكامل">
                                        <i class="fas fa-folder-open"></i>
                                    </a>
                                    <?php if ($can_edit): ?>
                                    <a href="edit_agent.php?id=<?php echo $row['id']; ?>" 
                                       class="action-btn edit" data-tooltip="تعديل">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php endif; ?>
                                    <?php if ($can_delete): ?>
                                    <a href="admin_agents.php?delete=<?php echo $row['id']; ?>&token=<?php echo $csrf_token; ?>" 
                                       class="action-btn delete" 
                                       data-confirm="هل تريد حذف هذا السائق؟">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 60px; color: var(--text-muted);">
                                <i class="fas fa-motorcycle" style="font-size: 50px; margin-bottom: 20px; display: block;"></i>
                                لا يوجد سائقين مسجلين
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
/* Responsive Styles for Agents */
@media (max-width: 1024px) {
    div[style*="grid-template-columns: 1fr 2fr"],
    div[style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
    }
}

@media (max-width: 768px) {
    /* Table Responsive */
    .table-container {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .admin-table {
        min-width: 600px;
    }
    
    .admin-table th,
    .admin-table td {
        padding: 10px 8px;
        font-size: 13px;
    }
    
    /* Agent Avatar */
    .admin-table td > div[style*="display: flex"] {
        gap: 8px !important;
    }
    
    .admin-table td > div[style*="display: flex"] > div[style*="width: 42px"] {
        width: 36px !important;
        height: 36px !important;
        font-size: 14px;
    }
    
    /* Action Buttons */
    .action-btns {
        flex-wrap: nowrap;
        gap: 5px;
    }
    
    .action-btn {
        width: 32px;
        height: 32px;
        font-size: 12px;
    }
    
    /* Form Styles */
    .card-body {
        padding: 15px;
    }
    
    .form-group {
        margin-bottom: 15px;
    }
    
    .form-control {
        padding: 10px 12px;
        font-size: 14px;
    }
    
    /* Documents Section */
    div[style*="background: var(--bg-hover)"] {
        padding: 12px !important;
    }
    
    div[style*="background: var(--bg-hover)"] h4 {
        font-size: 13px !important;
    }
    
    /* Card Header */
    .card-header {
        padding: 15px;
    }
    
    .card-title {
        font-size: 15px;
    }
}

@media (max-width: 480px) {
    .card-body {
        padding: 12px;
    }
    
    .admin-table th,
    .admin-table td {
        padding: 8px 6px;
        font-size: 12px;
    }
    
    /* Hide less important columns on very small screens */
    .admin-table th:nth-child(3),
    .admin-table td:nth-child(3) {
        display: none;
    }
    
    .badge {
        font-size: 10px;
        padding: 3px 8px;
    }
    
    .btn-success {
        padding: 12px 15px;
        font-size: 14px;
    }
}
</style>

<?php include 'includes/admin_footer.php'; ?>

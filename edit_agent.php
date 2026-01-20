<?php
include '../app/config/database.php';
include 'admin_auth.php';

checkAdminAuth();
$csrf_token = getAdminCSRF();

if (!isset($_GET['id'])) {
    header("Location: admin_agents.php");
    exit();
}

$id = intval($_GET['id']);
$agent = $conn->query("SELECT * FROM delivery_agents WHERE id = $id")->fetch_assoc();

if (!$agent) {
    header("Location: admin_agents.php");
    exit();
}

$page_title = 'تعديل السائق: ' . mb_substr($agent['name'], 0, 15);
$page_icon = 'fa-user-edit';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyAdminCSRF($_POST['csrf_token'])) {
        $message = 'طلب غير صالح!';
        $message_type = 'danger';
    } else {
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $password = $_POST['password'];
        $g_name = trim($_POST['guarantor_name']);

        // تشفير كلمة المرور إذا تغيرت
        if ($password !== $agent['password'] && !password_get_info($agent['password'])['algo']) {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        } else {
            $hashed_password = $password;
        }
        
        $stmt = $conn->prepare("UPDATE delivery_agents SET name=?, phone=?, password=?, guarantor_name=? WHERE id=?");
        $stmt->bind_param("ssssi", $name, $phone, $hashed_password, $g_name, $id);

        if ($stmt->execute()) {
            // رفع الصور الجديدة
            function updateFile($fileInputName, $dbColumn, $id, $conn) {
                if (!empty($_FILES[$fileInputName]['name'])) {
                    $target_dir = "../public/uploads/";
                    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
                    $file_ext = pathinfo($_FILES[$fileInputName]["name"], PATHINFO_EXTENSION);
                    $new_name = uniqid() . "." . $file_ext;
                    $target_file = $target_dir . $new_name;
                    
                    if (move_uploaded_file($_FILES[$fileInputName]["tmp_name"], $target_file)) {
                        $conn->query("UPDATE delivery_agents SET $dbColumn = '$target_file' WHERE id=$id");
                        return true;
                    }
                }
                return false;
            }

            updateFile('driver_id_img', 'driver_id_img', $id, $conn);
            updateFile('guarantor_id_img', 'guarantor_id_img', $id, $conn);
            updateFile('guarantee_img', 'guarantee_img', $id, $conn);

            $message = 'تم تحديث البيانات بنجاح!';
            $message_type = 'success';
            logAdminAction('edit_agent', "تم تعديل بيانات السائق رقم: $id");
            
            // تحديث البيانات المعروضة
            $agent = $conn->query("SELECT * FROM delivery_agents WHERE id = $id")->fetch_assoc();
        } else {
            $message = 'حدث خطأ في التحديث';
            $message_type = 'danger';
        }
        $stmt->close();
    }
}

include 'includes/admin_header.php';
?>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?>">
    <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
    <?php echo $message; ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-user-edit"></i>
            تعديل بيانات السائق
        </h3>
        <a href="admin_agent_details.php?id=<?php echo $id; ?>" class="btn btn-sm btn-outline">
            <i class="fas fa-folder-open"></i> الملف الكامل
        </a>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label">اسم السائق</label>
                    <input type="text" name="name" class="form-control" required 
                           value="<?php echo htmlspecialchars($agent['name']); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">رقم الهاتف</label>
                    <input type="tel" name="phone" class="form-control" required 
                           value="<?php echo htmlspecialchars($agent['phone']); ?>">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label">كلمة المرور</label>
                    <input type="text" name="password" class="form-control" required 
                           value="<?php echo htmlspecialchars($agent['password']); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">اسم الضامن</label>
                    <input type="text" name="guarantor_name" class="form-control" 
                           value="<?php echo htmlspecialchars($agent['guarantor_name'] ?? ''); ?>">
                </div>
            </div>
            
            <div style="background: var(--bg-hover); padding: 20px; border-radius: var(--radius-md); margin: 20px 0;">
                <h4 style="color: var(--text-primary); font-size: 14px; margin-bottom: 20px;">
                    <i class="fas fa-file-image"></i>
                    تحديث الوثائق (اختياري)
                </h4>
                
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                    <?php 
                    $docs = [
                        ['driver_id_img', 'بطاقة السائق'],
                        ['guarantor_id_img', 'بطاقة الضامن'],
                        ['guarantee_img', 'ورقة الضمانة']
                    ];
                    foreach ($docs as $doc):
                    ?>
                    <div>
                        <label class="form-label" style="font-size: 12px;"><?php echo $doc[1]; ?></label>
                        <?php if (!empty($agent[$doc[0]])): ?>
                            <div style="margin-bottom: 10px;">
                                <a href="<?php echo $agent[$doc[0]]; ?>" target="_blank" class="btn btn-sm btn-outline" style="width: 100%;">
                                    <i class="fas fa-eye"></i> عرض الحالية
                                </a>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="<?php echo $doc[0]; ?>" class="form-control" accept="image/*">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div style="display: flex; gap: 15px;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">
                    <i class="fas fa-save"></i>
                    حفظ التغييرات
                </button>
                <a href="admin_agents.php" class="btn btn-outline">
                    <i class="fas fa-times"></i>
                    إلغاء
                </a>
            </div>
        </form>
    </div>
</div>

<style>
/* Responsive Styles for Edit Agent */
@media (max-width: 768px) {
    div[style*="grid-template-columns: 1fr 1fr"],
    div[style*="grid-template-columns: repeat(3, 1fr)"] {
        grid-template-columns: 1fr !important;
    }
    
    .card-header {
        flex-direction: column;
        gap: 10px;
        align-items: flex-start !important;
    }
    
    .card-header .btn {
        width: 100%;
        justify-content: center;
    }
    
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
    
    div[style*="background: var(--bg-hover)"] {
        padding: 15px !important;
        margin: 15px 0 !important;
    }
    
    div[style*="display: flex; gap: 15px"] {
        flex-direction: column;
    }
    
    div[style*="display: flex; gap: 15px"] .btn {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .card-title {
        font-size: 14px;
    }
    
    .form-label {
        font-size: 13px;
    }
}
</style>

<?php include 'includes/admin_footer.php'; ?>

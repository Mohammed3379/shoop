<?php
/**
 * إدارة طرق الدفع
 */
include '../app/config/database.php';
include 'admin_auth.php';

checkAdminAuth();
requirePermission('settings.view');

$page_title = 'طرق الدفع';
$page_icon = 'fa-credit-card';
$csrf_token = getAdminCSRF();
$message = '';
$message_type = '';

$can_edit = hasPermission('settings.edit');

// إنشاء الجدول إذا لم يكن موجوداً
$conn->query("CREATE TABLE IF NOT EXISTS `payment_methods` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `name_ar` VARCHAR(100) NOT NULL,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `description` TEXT,
    `icon` VARCHAR(100) DEFAULT 'fa-credit-card',
    `type` ENUM('cash', 'card', 'wallet', 'bank', 'other') DEFAULT 'other',
    `fee_type` ENUM('fixed', 'percentage', 'none') DEFAULT 'none',
    `fee_amount` DECIMAL(10,2) DEFAULT 0,
    `min_order` DECIMAL(10,2) DEFAULT 0,
    `max_order` DECIMAL(10,2) DEFAULT 0,
    `instructions` TEXT,
    `sort_order` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// إدراج البيانات الافتراضية
$check = $conn->query("SELECT COUNT(*) as c FROM payment_methods");
if ($check->fetch_assoc()['c'] == 0) {
    $conn->query("INSERT INTO payment_methods (name, name_ar, code, description, icon, type, sort_order, is_active) VALUES
        ('Cash on Delivery', 'الدفع عند الاستلام', 'cod', 'ادفع نقداً عند استلام طلبك', 'fa-money-bill-wave', 'cash', 1, 1),
        ('Credit Card', 'بطاقة ائتمانية', 'card', 'ادفع باستخدام فيزا أو ماستركارد', 'fa-credit-card', 'card', 2, 1),
        ('Bank Transfer', 'تحويل بنكي', 'bank', 'حول المبلغ لحسابنا البنكي', 'fa-university', 'bank', 3, 1)");
}

// معالجة الطلبات
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_edit) {
    if (!verifyAdminCSRF($_POST['csrf_token'] ?? '')) {
        $message = 'طلب غير صالح!';
        $message_type = 'danger';
    } else {
        // إضافة طريقة دفع
        if (isset($_POST['add_payment'])) {
            $name = trim($_POST['name']);
            $name_ar = trim($_POST['name_ar']);
            $code = trim($_POST['code']);
            $description = trim($_POST['description']);
            $icon = trim($_POST['icon']) ?: 'fa-credit-card';
            $type = $_POST['type'];
            $fee_type = $_POST['fee_type'];
            $fee_amount = floatval($_POST['fee_amount']);
            $min_order = floatval($_POST['min_order']);
            $max_order = floatval($_POST['max_order']);
            $instructions = trim($_POST['instructions']);
            $sort_order = intval($_POST['sort_order']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            $stmt = $conn->prepare("INSERT INTO payment_methods (name, name_ar, code, description, icon, type, fee_type, fee_amount, min_order, max_order, instructions, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssdddsii", $name, $name_ar, $code, $description, $icon, $type, $fee_type, $fee_amount, $min_order, $max_order, $instructions, $sort_order, $is_active);
            
            if ($stmt->execute()) {
                $message = 'تم إضافة طريقة الدفع بنجاح';
                $message_type = 'success';
                logActivity('create', "إضافة طريقة دفع: $name_ar", 'payments', $conn->insert_id);
            } else {
                $message = 'فشل الإضافة: ' . $conn->error;
                $message_type = 'danger';
            }
        }
        
        // تعديل طريقة دفع
        if (isset($_POST['edit_payment'])) {
            $id = intval($_POST['payment_id']);
            $name = trim($_POST['name']);
            $name_ar = trim($_POST['name_ar']);
            $description = trim($_POST['description']);
            $icon = trim($_POST['icon']) ?: 'fa-credit-card';
            $type = $_POST['type'];
            $fee_type = $_POST['fee_type'];
            $fee_amount = floatval($_POST['fee_amount']);
            $min_order = floatval($_POST['min_order']);
            $max_order = floatval($_POST['max_order']);
            $instructions = trim($_POST['instructions']);
            $sort_order = intval($_POST['sort_order']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            $stmt = $conn->prepare("UPDATE payment_methods SET name=?, name_ar=?, description=?, icon=?, type=?, fee_type=?, fee_amount=?, min_order=?, max_order=?, instructions=?, sort_order=?, is_active=? WHERE id=?");
            $stmt->bind_param("ssssssdddsiii", $name, $name_ar, $description, $icon, $type, $fee_type, $fee_amount, $min_order, $max_order, $instructions, $sort_order, $is_active, $id);
            
            if ($stmt->execute()) {
                $message = 'تم تحديث طريقة الدفع بنجاح';
                $message_type = 'success';
                logActivity('update', "تعديل طريقة دفع: $name_ar", 'payments', $id);
            }
        }
        
        // تبديل الحالة
        if (isset($_POST['toggle_status'])) {
            $id = intval($_POST['payment_id']);
            $stmt = $conn->prepare("UPDATE payment_methods SET is_active = NOT is_active WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            $message = 'تم تغيير الحالة';
            $message_type = 'success';
        }
    }
}

// حذف
if (isset($_GET['delete']) && isset($_GET['token']) && $can_edit) {
    if (verifyAdminCSRF($_GET['token'])) {
        $id = intval($_GET['delete']);
        $stmt = $conn->prepare("DELETE FROM payment_methods WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $message = 'تم حذف طريقة الدفع';
        $message_type = 'success';
        logActivity('delete', "حذف طريقة دفع", 'payments', $id);
    }
}

// جلب طرق الدفع
$payments = $conn->query("SELECT * FROM payment_methods ORDER BY sort_order, id");

include 'includes/admin_header.php';
?>

<style>
.payments-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}
.payment-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    padding: 25px;
    position: relative;
    transition: all 0.3s;
}
.payment-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}
.payment-card.inactive { opacity: 0.5; }
.payment-icon {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin-bottom: 15px;
}
.payment-icon.cash { background: linear-gradient(135deg, #10b981, #059669); color: white; }
.payment-icon.card { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; }
.payment-icon.wallet { background: linear-gradient(135deg, #8b5cf6, #6d28d9); color: white; }
.payment-icon.bank { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
.payment-icon.other { background: linear-gradient(135deg, #6b7280, #4b5563); color: white; }
.payment-name { font-size: 18px; font-weight: 600; margin-bottom: 5px; }
.payment-desc { color: var(--text-muted); font-size: 13px; margin-bottom: 15px; }
.payment-meta { display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 15px; }
.payment-meta span {
    font-size: 12px;
    padding: 5px 10px;
    background: var(--bg-hover);
    border-radius: 20px;
    color: var(--text-secondary);
}
.payment-actions { display: flex; gap: 10px; }
.payment-actions button, .payment-actions a {
    flex: 1;
    padding: 10px;
    border: none;
    border-radius: var(--radius-md);
    cursor: pointer;
    font-size: 13px;
    text-align: center;
    text-decoration: none;
    transition: all 0.2s;
}
.btn-toggle-on { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.btn-toggle-off { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
.btn-edit { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
.btn-delete { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
.status-badge {
    position: absolute;
    top: 15px;
    left: 15px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}
.status-active { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.status-inactive { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
.add-card {
    background: var(--bg-hover);
    border: 2px dashed var(--border-color);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    min-height: 250px;
}
.add-card:hover { border-color: var(--primary); }
.add-card i { font-size: 40px; color: var(--primary); margin-bottom: 10px; }

/* Modal */
.modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1000; align-items: center; justify-content: center; }
.modal.active { display: flex; }
.modal-content { background: var(--bg-card); border-radius: var(--radius-lg); width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto; }
.modal-header { padding: 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
.modal-header h3 { margin: 0; }
.modal-close { background: none; border: none; font-size: 24px; color: var(--text-muted); cursor: pointer; }
.modal-body { padding: 20px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
.form-group { margin-bottom: 15px; }
.form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
.form-control { width: 100%; padding: 10px 12px; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-md); color: var(--text-primary); }
.modal-footer { padding: 20px; border-top: 1px solid var(--border-color); display: flex; gap: 10px; justify-content: flex-end; }
.btn-primary { background: var(--primary); color: white; border: none; padding: 12px 25px; border-radius: var(--radius-md); cursor: pointer; }
.btn-secondary { background: var(--bg-hover); color: var(--text-primary); border: 1px solid var(--border-color); padding: 12px 25px; border-radius: var(--radius-md); cursor: pointer; }

/* تحسينات التجاوب */
@media (max-width: 768px) {
    .payments-grid {
        grid-template-columns: 1fr !important;
    }
    
    .payment-card {
        padding: 20px;
    }
    
    .modal-content {
        margin: 15px;
        max-width: calc(100% - 30px);
    }
    
    .form-row {
        grid-template-columns: 1fr !important;
    }
}

@media (max-width: 576px) {
    .payment-actions {
        flex-direction: column;
    }
    
    .payment-actions button,
    .payment-actions a {
        width: 100%;
    }
    
    .payment-meta {
        flex-direction: column;
        gap: 8px;
    }
}
</style>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?>">
    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
    <?php echo $message; ?>
</div>
<?php endif; ?>

<div class="payments-grid">
    <?php if ($can_edit): ?>
    <div class="payment-card add-card" onclick="openModal('add')">
        <i class="fas fa-plus-circle"></i>
        <span>إضافة طريقة دفع جديدة</span>
    </div>
    <?php endif; ?>
    
    <?php while ($p = $payments->fetch_assoc()): ?>
    <div class="payment-card <?php echo $p['is_active'] ? '' : 'inactive'; ?>">
        <span class="status-badge <?php echo $p['is_active'] ? 'status-active' : 'status-inactive'; ?>">
            <?php echo $p['is_active'] ? 'مفعّل' : 'معطّل'; ?>
        </span>
        
        <div class="payment-icon <?php echo $p['type']; ?>">
            <i class="fas <?php echo $p['icon']; ?>"></i>
        </div>
        
        <div class="payment-name"><?php echo htmlspecialchars($p['name_ar']); ?></div>
        <div class="payment-desc"><?php echo htmlspecialchars($p['description']); ?></div>
        
        <div class="payment-meta">
            <span><i class="fas fa-tag"></i> <?php echo $p['code']; ?></span>
            <?php if ($p['fee_type'] !== 'none' && $p['fee_amount'] > 0): ?>
            <span><i class="fas fa-percent"></i> رسوم: <?php echo $p['fee_amount']; ?><?php echo $p['fee_type'] === 'percentage' ? '%' : ' ر.س'; ?></span>
            <?php endif; ?>
            <?php if ($p['min_order'] > 0): ?>
            <span><i class="fas fa-arrow-down"></i> حد أدنى: <?php echo $p['min_order']; ?></span>
            <?php endif; ?>
        </div>
        
        <?php if ($can_edit): ?>
        <div class="payment-actions">
            <form method="POST" style="flex:1;">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="payment_id" value="<?php echo $p['id']; ?>">
                <button type="submit" name="toggle_status" class="<?php echo $p['is_active'] ? 'btn-toggle-on' : 'btn-toggle-off'; ?>" style="width:100%;">
                    <i class="fas fa-<?php echo $p['is_active'] ? 'toggle-on' : 'toggle-off'; ?>"></i>
                    <?php echo $p['is_active'] ? 'تعطيل' : 'تفعيل'; ?>
                </button>
            </form>
            <button class="btn-edit" onclick='openModal("edit", <?php echo json_encode($p); ?>)'>
                <i class="fas fa-edit"></i>
            </button>
            <a href="?delete=<?php echo $p['id']; ?>&token=<?php echo $csrf_token; ?>" class="btn-delete" onclick="return confirm('حذف طريقة الدفع؟')">
                <i class="fas fa-trash"></i>
            </a>
        </div>
        <?php endif; ?>
    </div>
    <?php endwhile; ?>
</div>

<!-- Modal -->
<div class="modal" id="paymentModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle"><i class="fas fa-credit-card"></i> إضافة طريقة دفع</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST" id="paymentForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="payment_id" id="paymentId">
            
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>الاسم بالإنجليزية</label>
                        <input type="text" name="name" id="pName" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>الاسم بالعربية</label>
                        <input type="text" name="name_ar" id="pNameAr" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>الكود</label>
                        <input type="text" name="code" id="pCode" class="form-control" required placeholder="مثال: cod">
                    </div>
                    <div class="form-group">
                        <label>الأيقونة</label>
                        <input type="text" name="icon" id="pIcon" class="form-control" value="fa-credit-card">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>الوصف</label>
                    <textarea name="description" id="pDesc" class="form-control" rows="2"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>النوع</label>
                        <select name="type" id="pType" class="form-control">
                            <option value="cash">نقدي</option>
                            <option value="card">بطاقة</option>
                            <option value="wallet">محفظة</option>
                            <option value="bank">بنكي</option>
                            <option value="other">أخرى</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>الترتيب</label>
                        <input type="number" name="sort_order" id="pOrder" class="form-control" value="0">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>نوع الرسوم</label>
                        <select name="fee_type" id="pFeeType" class="form-control">
                            <option value="none">بدون رسوم</option>
                            <option value="fixed">مبلغ ثابت</option>
                            <option value="percentage">نسبة مئوية</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>قيمة الرسوم</label>
                        <input type="number" name="fee_amount" id="pFeeAmount" class="form-control" value="0" step="0.01">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>الحد الأدنى للطلب</label>
                        <input type="number" name="min_order" id="pMinOrder" class="form-control" value="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>الحد الأقصى للطلب</label>
                        <input type="number" name="max_order" id="pMaxOrder" class="form-control" value="0" step="0.01">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>تعليمات للعميل</label>
                    <textarea name="instructions" id="pInstructions" class="form-control" rows="2"></textarea>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox" name="is_active" id="pActive" checked>
                        <span>مفعّل</span>
                    </label>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal()">إلغاء</button>
                <button type="submit" name="add_payment" id="submitBtn" class="btn-primary">إضافة</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(mode, data = null) {
    const modal = document.getElementById('paymentModal');
    const form = document.getElementById('paymentForm');
    const title = document.getElementById('modalTitle');
    const submitBtn = document.getElementById('submitBtn');
    
    form.reset();
    document.getElementById('pActive').checked = true;
    
    if (mode === 'edit' && data) {
        title.innerHTML = '<i class="fas fa-edit"></i> تعديل طريقة الدفع';
        submitBtn.name = 'edit_payment';
        submitBtn.textContent = 'حفظ التغييرات';
        
        document.getElementById('paymentId').value = data.id;
        document.getElementById('pName').value = data.name;
        document.getElementById('pNameAr').value = data.name_ar;
        document.getElementById('pCode').value = data.code;
        document.getElementById('pCode').readOnly = true;
        document.getElementById('pIcon').value = data.icon;
        document.getElementById('pDesc').value = data.description || '';
        document.getElementById('pType').value = data.type;
        document.getElementById('pOrder').value = data.sort_order;
        document.getElementById('pFeeType').value = data.fee_type;
        document.getElementById('pFeeAmount').value = data.fee_amount;
        document.getElementById('pMinOrder').value = data.min_order;
        document.getElementById('pMaxOrder').value = data.max_order;
        document.getElementById('pInstructions').value = data.instructions || '';
        document.getElementById('pActive').checked = data.is_active == 1;
    } else {
        title.innerHTML = '<i class="fas fa-plus"></i> إضافة طريقة دفع';
        submitBtn.name = 'add_payment';
        submitBtn.textContent = 'إضافة';
        document.getElementById('pCode').readOnly = false;
    }
    
    modal.classList.add('active');
}

function closeModal() {
    document.getElementById('paymentModal').classList.remove('active');
}

document.getElementById('paymentModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>

<?php include 'includes/admin_footer.php'; ?>

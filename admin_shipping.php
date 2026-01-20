<?php
/**
 * إدارة طرق الشحن
 */
include '../app/config/database.php';
include 'admin_auth.php';

checkAdminAuth();
requirePermission('settings.view');

$page_title = 'طرق الشحن';
$page_icon = 'fa-truck';
$csrf_token = getAdminCSRF();
$message = '';
$message_type = '';

$can_edit = hasPermission('settings.edit');

// إنشاء الجدول إذا لم يكن موجوداً
$conn->query("CREATE TABLE IF NOT EXISTS `shipping_methods` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `name_ar` VARCHAR(100) NOT NULL,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `description` TEXT,
    `icon` VARCHAR(100) DEFAULT 'fa-truck',
    `type` ENUM('flat', 'per_item', 'per_weight', 'free', 'calculated') DEFAULT 'flat',
    `cost` DECIMAL(10,2) DEFAULT 0,
    `free_shipping_min` DECIMAL(10,2) DEFAULT 0,
    `estimated_days_min` INT DEFAULT 1,
    `estimated_days_max` INT DEFAULT 3,
    `sort_order` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// إدراج البيانات الافتراضية
$check = $conn->query("SELECT COUNT(*) as c FROM shipping_methods");
if ($check->fetch_assoc()['c'] == 0) {
    $conn->query("INSERT INTO shipping_methods (name, name_ar, code, description, icon, type, cost, free_shipping_min, estimated_days_min, estimated_days_max, sort_order, is_active) VALUES
        ('Standard Shipping', 'شحن عادي', 'standard', 'توصيل خلال 3-5 أيام عمل', 'fa-truck', 'flat', 25.00, 200, 3, 5, 1, 1),
        ('Express Shipping', 'شحن سريع', 'express', 'توصيل خلال 1-2 يوم عمل', 'fa-shipping-fast', 'flat', 50.00, 500, 1, 2, 2, 1),
        ('Free Shipping', 'شحن مجاني', 'free', 'شحن مجاني للطلبات فوق 200 ريال', 'fa-gift', 'free', 0, 200, 3, 7, 3, 1)");
}

// معالجة الطلبات
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_edit) {
    if (!verifyAdminCSRF($_POST['csrf_token'] ?? '')) {
        $message = 'طلب غير صالح!';
        $message_type = 'danger';
    } else {
        // إضافة طريقة شحن
        if (isset($_POST['add_shipping'])) {
            $name = trim($_POST['name']);
            $name_ar = trim($_POST['name_ar']);
            $code = trim($_POST['code']);
            $description = trim($_POST['description']);
            $icon = trim($_POST['icon']) ?: 'fa-truck';
            $type = $_POST['type'];
            $cost = floatval($_POST['cost']);
            $free_shipping_min = floatval($_POST['free_shipping_min']);
            $estimated_days_min = intval($_POST['estimated_days_min']);
            $estimated_days_max = intval($_POST['estimated_days_max']);
            $sort_order = intval($_POST['sort_order']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            $stmt = $conn->prepare("INSERT INTO shipping_methods (name, name_ar, code, description, icon, type, cost, free_shipping_min, estimated_days_min, estimated_days_max, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssddiiis", $name, $name_ar, $code, $description, $icon, $type, $cost, $free_shipping_min, $estimated_days_min, $estimated_days_max, $sort_order, $is_active);
            
            if ($stmt->execute()) {
                $message = 'تم إضافة طريقة الشحن بنجاح';
                $message_type = 'success';
                logActivity('create', "إضافة طريقة شحن: $name_ar", 'shipping', $conn->insert_id);
            } else {
                $message = 'فشل الإضافة: ' . $conn->error;
                $message_type = 'danger';
            }
        }
        
        // تعديل طريقة شحن
        if (isset($_POST['edit_shipping'])) {
            $id = intval($_POST['shipping_id']);
            $name = trim($_POST['name']);
            $name_ar = trim($_POST['name_ar']);
            $description = trim($_POST['description']);
            $icon = trim($_POST['icon']) ?: 'fa-truck';
            $type = $_POST['type'];
            $cost = floatval($_POST['cost']);
            $free_shipping_min = floatval($_POST['free_shipping_min']);
            $estimated_days_min = intval($_POST['estimated_days_min']);
            $estimated_days_max = intval($_POST['estimated_days_max']);
            $sort_order = intval($_POST['sort_order']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            $stmt = $conn->prepare("UPDATE shipping_methods SET name=?, name_ar=?, description=?, icon=?, type=?, cost=?, free_shipping_min=?, estimated_days_min=?, estimated_days_max=?, sort_order=?, is_active=? WHERE id=?");
            $stmt->bind_param("sssssddiiiii", $name, $name_ar, $description, $icon, $type, $cost, $free_shipping_min, $estimated_days_min, $estimated_days_max, $sort_order, $is_active, $id);
            
            if ($stmt->execute()) {
                $message = 'تم تحديث طريقة الشحن بنجاح';
                $message_type = 'success';
                logActivity('update', "تعديل طريقة شحن: $name_ar", 'shipping', $id);
            }
        }
        
        // تبديل الحالة
        if (isset($_POST['toggle_status'])) {
            $id = intval($_POST['shipping_id']);
            $stmt = $conn->prepare("UPDATE shipping_methods SET is_active = NOT is_active WHERE id = ?");
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
        $stmt = $conn->prepare("DELETE FROM shipping_methods WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $message = 'تم حذف طريقة الشحن';
        $message_type = 'success';
        logActivity('delete', "حذف طريقة شحن", 'shipping', $id);
    }
}

// جلب طرق الشحن
$shipping = $conn->query("SELECT * FROM shipping_methods ORDER BY sort_order, id");

include 'includes/admin_header.php';
?>

<style>
.shipping-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}
.shipping-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    padding: 25px;
    position: relative;
    transition: all 0.3s;
}
.shipping-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}
.shipping-card.inactive { opacity: 0.5; }
.shipping-icon {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    background: linear-gradient(135deg, var(--primary), #4f46e5);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
    margin-bottom: 15px;
}
.shipping-name { font-size: 18px; font-weight: 600; margin-bottom: 5px; }
.shipping-desc { color: var(--text-muted); font-size: 13px; margin-bottom: 15px; }
.shipping-meta { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 15px; }
.shipping-meta span {
    font-size: 12px;
    padding: 5px 10px;
    background: var(--bg-hover);
    border-radius: 20px;
    color: var(--text-secondary);
}
.shipping-cost {
    font-size: 24px;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 15px;
}
.shipping-cost small { font-size: 14px; color: var(--text-muted); font-weight: normal; }
.shipping-actions { display: flex; gap: 10px; }
.shipping-actions button, .shipping-actions a {
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
    min-height: 300px;
}
.add-card:hover { border-color: var(--primary); }
.add-card i { font-size: 40px; color: var(--primary); margin-bottom: 10px; }
.free-badge {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    margin-right: 10px;
}

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
    .shipping-grid {
        grid-template-columns: 1fr !important;
    }
    
    .shipping-card {
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
    .shipping-actions {
        flex-direction: column;
    }
    
    .shipping-actions button,
    .shipping-actions a {
        width: 100%;
    }
    
    .shipping-meta {
        flex-direction: column;
        gap: 8px;
    }
    
    .shipping-cost {
        font-size: 20px;
    }
}
</style>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?>">
    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
    <?php echo $message; ?>
</div>
<?php endif; ?>

<div class="shipping-grid">
    <?php if ($can_edit): ?>
    <div class="shipping-card add-card" onclick="openModal('add')">
        <i class="fas fa-plus-circle"></i>
        <span>إضافة طريقة شحن جديدة</span>
    </div>
    <?php endif; ?>
    
    <?php while ($s = $shipping->fetch_assoc()): ?>
    <div class="shipping-card <?php echo $s['is_active'] ? '' : 'inactive'; ?>">
        <span class="status-badge <?php echo $s['is_active'] ? 'status-active' : 'status-inactive'; ?>">
            <?php echo $s['is_active'] ? 'مفعّل' : 'معطّل'; ?>
        </span>
        
        <div class="shipping-icon">
            <i class="fas <?php echo $s['icon']; ?>"></i>
        </div>
        
        <div class="shipping-name"><?php echo htmlspecialchars($s['name_ar']); ?></div>
        <div class="shipping-desc"><?php echo htmlspecialchars($s['description']); ?></div>
        
        <div class="shipping-cost">
            <?php if ($s['type'] === 'free'): ?>
                مجاني
                <?php if ($s['free_shipping_min'] > 0): ?>
                <small>للطلبات فوق <?php echo $s['free_shipping_min']; ?> ر.س</small>
                <?php endif; ?>
            <?php else: ?>
                <?php echo number_format($s['cost'], 2); ?> <small>ر.س</small>
                <?php if ($s['free_shipping_min'] > 0): ?>
                <span class="free-badge">مجاني فوق <?php echo $s['free_shipping_min']; ?></span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <div class="shipping-meta">
            <span><i class="fas fa-clock"></i> <?php echo $s['estimated_days_min']; ?>-<?php echo $s['estimated_days_max']; ?> أيام</span>
            <span><i class="fas fa-tag"></i> <?php echo $s['code']; ?></span>
        </div>
        
        <?php if ($can_edit): ?>
        <div class="shipping-actions">
            <form method="POST" style="flex:1;">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="shipping_id" value="<?php echo $s['id']; ?>">
                <button type="submit" name="toggle_status" class="<?php echo $s['is_active'] ? 'btn-toggle-on' : 'btn-toggle-off'; ?>" style="width:100%;">
                    <i class="fas fa-<?php echo $s['is_active'] ? 'toggle-on' : 'toggle-off'; ?>"></i>
                    <?php echo $s['is_active'] ? 'تعطيل' : 'تفعيل'; ?>
                </button>
            </form>
            <button class="btn-edit" onclick='openModal("edit", <?php echo json_encode($s); ?>)'>
                <i class="fas fa-edit"></i>
            </button>
            <a href="?delete=<?php echo $s['id']; ?>&token=<?php echo $csrf_token; ?>" class="btn-delete" onclick="return confirm('حذف طريقة الشحن؟')">
                <i class="fas fa-trash"></i>
            </a>
        </div>
        <?php endif; ?>
    </div>
    <?php endwhile; ?>
</div>

<!-- Modal -->
<div class="modal" id="shippingModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle"><i class="fas fa-truck"></i> إضافة طريقة شحن</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST" id="shippingForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="shipping_id" id="shippingId">
            
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>الاسم بالإنجليزية</label>
                        <input type="text" name="name" id="sName" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>الاسم بالعربية</label>
                        <input type="text" name="name_ar" id="sNameAr" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>الكود</label>
                        <input type="text" name="code" id="sCode" class="form-control" required placeholder="مثال: standard">
                    </div>
                    <div class="form-group">
                        <label>الأيقونة</label>
                        <input type="text" name="icon" id="sIcon" class="form-control" value="fa-truck">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>الوصف</label>
                    <textarea name="description" id="sDesc" class="form-control" rows="2"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>نوع التسعير</label>
                        <select name="type" id="sType" class="form-control">
                            <option value="flat">سعر ثابت</option>
                            <option value="per_item">لكل منتج</option>
                            <option value="per_weight">حسب الوزن</option>
                            <option value="free">مجاني</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>التكلفة (ر.س)</label>
                        <input type="number" name="cost" id="sCost" class="form-control" value="0" step="0.01">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>شحن مجاني للطلبات فوق</label>
                        <input type="number" name="free_shipping_min" id="sFreeMin" class="form-control" value="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>الترتيب</label>
                        <input type="number" name="sort_order" id="sOrder" class="form-control" value="0">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>أيام التوصيل (من)</label>
                        <input type="number" name="estimated_days_min" id="sDaysMin" class="form-control" value="1" min="0">
                    </div>
                    <div class="form-group">
                        <label>أيام التوصيل (إلى)</label>
                        <input type="number" name="estimated_days_max" id="sDaysMax" class="form-control" value="3" min="0">
                    </div>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox" name="is_active" id="sActive" checked>
                        <span>مفعّل</span>
                    </label>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal()">إلغاء</button>
                <button type="submit" name="add_shipping" id="submitBtn" class="btn-primary">إضافة</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(mode, data = null) {
    const modal = document.getElementById('shippingModal');
    const form = document.getElementById('shippingForm');
    const title = document.getElementById('modalTitle');
    const submitBtn = document.getElementById('submitBtn');
    
    form.reset();
    document.getElementById('sActive').checked = true;
    
    if (mode === 'edit' && data) {
        title.innerHTML = '<i class="fas fa-edit"></i> تعديل طريقة الشحن';
        submitBtn.name = 'edit_shipping';
        submitBtn.textContent = 'حفظ التغييرات';
        
        document.getElementById('shippingId').value = data.id;
        document.getElementById('sName').value = data.name;
        document.getElementById('sNameAr').value = data.name_ar;
        document.getElementById('sCode').value = data.code;
        document.getElementById('sCode').readOnly = true;
        document.getElementById('sIcon').value = data.icon;
        document.getElementById('sDesc').value = data.description || '';
        document.getElementById('sType').value = data.type;
        document.getElementById('sCost').value = data.cost;
        document.getElementById('sFreeMin').value = data.free_shipping_min;
        document.getElementById('sOrder').value = data.sort_order;
        document.getElementById('sDaysMin').value = data.estimated_days_min;
        document.getElementById('sDaysMax').value = data.estimated_days_max;
        document.getElementById('sActive').checked = data.is_active == 1;
    } else {
        title.innerHTML = '<i class="fas fa-plus"></i> إضافة طريقة شحن';
        submitBtn.name = 'add_shipping';
        submitBtn.textContent = 'إضافة';
        document.getElementById('sCode').readOnly = false;
    }
    
    modal.classList.add('active');
}

function closeModal() {
    document.getElementById('shippingModal').classList.remove('active');
}

document.getElementById('shippingModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>

<?php include 'includes/admin_footer.php'; ?>

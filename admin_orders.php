<?php
include '../app/config/database.php';
include 'admin_auth.php';
include '../send_mail.php';

checkAdminAuth();

// التحقق من صلاحية عرض الطلبات
requirePermission('orders.view');

$page_title = 'إدارة الطلبات';
$page_icon = 'fa-shopping-bag';

$csrf_token = getAdminCSRF();
$message = '';
$message_type = '';

// صلاحيات المستخدم الحالي
$can_update_status = hasPermission('orders.update_status');
$can_delete = hasPermission('orders.delete');
$can_assign_agent = hasPermission('agents.assign_orders');

$allowed_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

// معالجة الطلبات
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyAdminCSRF($_POST['csrf_token'])) {
        $message = 'طلب غير صالح!';
        $message_type = 'danger';
    } else {
        // تحديث الحالة
        if (isset($_POST['update_status'])) {
            if (!$can_update_status) {
                $message = 'ليس لديك صلاحية تحديث حالة الطلبات';
                $message_type = 'danger';
            } else {
                $order_id = intval($_POST['order_id']);
                $new_status = $_POST['status'];
                
                if (in_array($new_status, $allowed_statuses)) {
                    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
                    $stmt->bind_param("si", $new_status, $order_id);
                    $stmt->execute();
                    $stmt->close();
                    
                    logAdminAction('update_order_status', "تحديث حالة الطلب #$order_id إلى: $new_status");
                    $message = 'تم تحديث حالة الطلب بنجاح!';
                    $message_type = 'success';
                }
            }
        }
        
        // تعيين السائق
        if (isset($_POST['assign_agent'])) {
            if (!$can_assign_agent) {
                $message = 'ليس لديك صلاحية تعيين السائقين';
                $message_type = 'danger';
            } else {
                $order_id = intval($_POST['order_id']);
                $agent_id = intval($_POST['agent_id']);
                
                $stmt = $conn->prepare("UPDATE orders SET delivery_agent_id = ? WHERE id = ?");
                $stmt->bind_param("ii", $agent_id, $order_id);
                $stmt->execute();
                $stmt->close();
                
                logAdminAction('assign_agent', "تعيين السائق #$agent_id للطلب #$order_id");
                $message = 'تم تعيين السائق بنجاح!';
                $message_type = 'success';
            }
        }
    }
}

// جلب السائقين
$agents = [];
$agents_result = $conn->query("SELECT id, name FROM delivery_agents WHERE status = 'active'");
if ($agents_result) {
    while ($a = $agents_result->fetch_assoc()) $agents[] = $a;
}

include 'includes/admin_header.php';
?>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?>">
    <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
    <?php echo $message; ?>
</div>
<?php endif; ?>

<!-- فلاتر -->
<div class="card" style="margin-bottom: 25px;">
    <div class="card-body" style="padding: 20px;">
        <div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">
            <span style="color: var(--text-muted); font-size: 14px;">
                <i class="fas fa-filter"></i> تصفية:
            </span>
            <a href="admin_orders.php" class="btn btn-sm <?php echo !isset($_GET['status']) ? 'btn-primary' : 'btn-outline'; ?>">
                الكل
            </a>
            <a href="?status=pending" class="btn btn-sm <?php echo ($_GET['status'] ?? '') == 'pending' ? 'btn-primary' : 'btn-outline'; ?>">
                قيد الانتظار
            </a>
            <a href="?status=shipped" class="btn btn-sm <?php echo ($_GET['status'] ?? '') == 'shipped' ? 'btn-primary' : 'btn-outline'; ?>">
                في الطريق
            </a>
            <a href="?status=delivered" class="btn btn-sm <?php echo ($_GET['status'] ?? '') == 'delivered' ? 'btn-primary' : 'btn-outline'; ?>">
                تم التوصيل
            </a>
        </div>
    </div>
</div>

<!-- جدول الطلبات -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-list"></i>
            قائمة الطلبات
        </h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>رقم الطلب</th>
                        <th>العميل</th>
                        <th>العنوان</th>
                        <th>الشحن/الدفع</th>
                        <th>المبلغ</th>
                        <th>الحالة</th>
                        <th>السائق</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT o.*, da.name as agent_name 
                            FROM orders o 
                            LEFT JOIN delivery_agents da ON o.delivery_agent_id = da.id";
                    
                    $where_params = [];
                    $where_types = "";
                    
                    if (isset($_GET['status']) && in_array($_GET['status'], $allowed_statuses)) {
                        $sql .= " WHERE o.status = ?";
                        $where_params[] = $_GET['status'];
                        $where_types .= "s";
                    }
                    
                    $sql .= " ORDER BY o.id DESC";
                    
                    $stmt = $conn->prepare($sql);
                    if (!empty($where_params)) {
                        $stmt->bind_param($where_types, ...$where_params);
                    }
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result && $result->num_rows > 0):
                        while ($row = $result->fetch_assoc()):
                            $status_badges = [
                                'pending' => ['badge-warning', 'قيد الانتظار', 'fa-clock'],
                                'processing' => ['badge-info', 'قيد التجهيز', 'fa-cog'],
                                'shipped' => ['badge-primary', 'في الطريق', 'fa-truck'],
                                'delivered' => ['badge-success', 'تم التوصيل', 'fa-check'],
                                'cancelled' => ['badge-danger', 'ملغي', 'fa-times']
                            ];
                            $badge = $status_badges[$row['status']] ?? ['badge-info', $row['status'], 'fa-info'];
                    ?>
                    <tr>
                        <td>
                            <span style="font-weight: 700; color: var(--primary);">#<?php echo $row['id']; ?></span>
                        </td>
                        <td>
                            <div>
                                <div style="font-weight: 600; color: var(--text-primary);">
                                    <?php echo htmlspecialchars($row['customer_name']); ?>
                                </div>
                                <div style="font-size: 12px; color: var(--text-muted);">
                                    <i class="fas fa-phone" style="font-size: 10px;"></i>
                                    <?php echo htmlspecialchars($row['phone']); ?>
                                </div>
                            </div>
                        </td>
                        <td style="max-width: 200px;">
                            <div style="font-size: 13px; color: var(--text-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                <?php echo htmlspecialchars(mb_substr($row['address'], 0, 40)); ?>...
                            </div>
                        </td>
                        <td>
                            <?php
                            $shipping_methods_text = ['standard'=>'عادي','express'=>'سريع','free'=>'مجاني','pickup'=>'استلام'];
                            $payment_methods_text = ['cod'=>'عند الاستلام','cash_on_delivery'=>'عند الاستلام','credit_card'=>'بطاقة','bank_transfer'=>'تحويل','wallet'=>'محفظة'];
                            $ship_txt = $shipping_methods_text[$row['shipping_method'] ?? 'standard'] ?? ($row['shipping_method'] ?? '-');
                            $pay_txt = $payment_methods_text[$row['payment_method'] ?? 'cod'] ?? ($row['payment_method'] ?? '-');
                            ?>
                            <div style="font-size: 12px;">
                                <div style="color: var(--text-secondary);"><i class="fas fa-truck" style="width:14px;"></i> <?php echo $ship_txt; ?></div>
                                <div style="color: var(--text-muted); margin-top:3px;"><i class="fas fa-credit-card" style="width:14px;"></i> <?php echo $pay_txt; ?></div>
                            </div>
                        </td>
                        <td>
                            <div>
                                <span style="font-weight: 700; color: var(--secondary);">
                                    <?php echo formatPrice($row['total_price']); ?>
                                </span>
                                <?php if (($row['shipping_cost'] ?? 0) > 0): ?>
                                <div style="font-size:11px; color:var(--text-muted);">شحن: <?php echo formatPrice($row['shipping_cost']); ?></div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge <?php echo $badge[0]; ?>">
                                <i class="fas <?php echo $badge[2]; ?>" style="font-size: 10px;"></i>
                                <?php echo $badge[1]; ?>
                            </span>
                        </td>
                        <td>
                            <form method="POST" style="display: flex; gap: 8px; align-items: center;">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="order_id" value="<?php echo $row['id']; ?>">
                                <select name="agent_id" class="form-control" style="padding: 8px 12px; min-width: 130px; font-size: 13px;">
                                    <option value="0">-- اختر سائق --</option>
                                    <?php foreach ($agents as $agent): ?>
                                        <option value="<?php echo $agent['id']; ?>" <?php echo ($row['delivery_agent_id'] == $agent['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($agent['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="assign_agent" class="btn btn-sm btn-primary" style="padding: 8px 12px;">
                                    <i class="fas fa-check"></i>
                                </button>
                            </form>
                        </td>
                        <td>
                            <div class="action-btns">
                                <a href="invoice.php?id=<?php echo $row['id']; ?>" target="_blank" 
                                   class="action-btn view" data-tooltip="طباعة الفاتورة">
                                    <i class="fas fa-print"></i>
                                </a>
                                
                                <!-- تغيير الحالة -->
                                <div style="position: relative;">
                                    <button class="action-btn edit" onclick="toggleStatusMenu(<?php echo $row['id']; ?>)" data-tooltip="تغيير الحالة">
                                        <i class="fas fa-exchange-alt"></i>
                                    </button>
                                    <div id="status-menu-<?php echo $row['id']; ?>" class="status-dropdown" style="display: none;">
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="order_id" value="<?php echo $row['id']; ?>">
                                            <?php foreach ($status_badges as $key => $val): ?>
                                                <button type="submit" name="update_status" value="1" onclick="this.form.status.value='<?php echo $key; ?>'" class="status-option">
                                                    <i class="fas <?php echo $val[2]; ?>"></i>
                                                    <?php echo $val[1]; ?>
                                                </button>
                                            <?php endforeach; ?>
                                            <input type="hidden" name="status" value="">
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php 
                        endwhile;
                    else:
                    ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 60px; color: var(--text-muted);">
                            <i class="fas fa-inbox" style="font-size: 50px; margin-bottom: 20px; display: block;"></i>
                            لا توجد طلبات
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.status-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 8px;
    min-width: 160px;
    z-index: 100;
    box-shadow: var(--shadow-lg);
}

.status-option {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 10px 12px;
    background: transparent;
    border: none;
    color: var(--text-secondary);
    font-size: 13px;
    cursor: pointer;
    border-radius: var(--radius-sm);
    transition: all var(--transition-fast);
    text-align: right;
}

.status-option:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}

.status-option i {
    width: 16px;
}

/* تحسينات التجاوب لصفحة الطلبات */
@media (max-width: 1200px) {
    .admin-table td:nth-child(3),
    .admin-table th:nth-child(3) {
        display: none; /* إخفاء عمود العنوان */
    }
}

@media (max-width: 992px) {
    .admin-table td:nth-child(4),
    .admin-table th:nth-child(4) {
        display: none; /* إخفاء عمود الشحن/الدفع */
    }
}

@media (max-width: 768px) {
    .admin-table td:nth-child(7),
    .admin-table th:nth-child(7) {
        display: none; /* إخفاء عمود السائق */
    }
    
    .card[style*="margin-bottom: 25px"] .card-body {
        padding: 15px;
    }
    
    .card[style*="margin-bottom: 25px"] .card-body > div {
        flex-direction: column;
        align-items: stretch !important;
    }
    
    .card[style*="margin-bottom: 25px"] .btn {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 576px) {
    .admin-table td:nth-child(6),
    .admin-table th:nth-child(6) {
        display: none; /* إخفاء عمود الحالة في الشاشات الصغيرة جداً */
    }
    
    .action-btns {
        flex-direction: column;
        gap: 5px;
    }
    
    .status-dropdown {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        min-width: 200px;
    }
}
</style>

<script>
function toggleStatusMenu(orderId) {
    // إغلاق جميع القوائم
    document.querySelectorAll('.status-dropdown').forEach(menu => {
        menu.style.display = 'none';
    });
    
    // فتح القائمة المطلوبة
    const menu = document.getElementById('status-menu-' + orderId);
    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
}

// إغلاق القائمة عند الضغط خارجها
document.addEventListener('click', function(e) {
    if (!e.target.closest('.action-btns')) {
        document.querySelectorAll('.status-dropdown').forEach(menu => {
            menu.style.display = 'none';
        });
    }
});
</script>

<?php include 'includes/admin_footer.php'; ?>

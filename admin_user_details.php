<?php
include '../app/config/database.php';
include 'admin_auth.php';

checkAdminAuth();
$csrf_token = getAdminCSRF();

if (!isset($_GET['id'])) {
    header("Location: admin_users.php");
    exit();
}

$user_id = intval($_GET['id']);

// تنفيذ إجراء التجميد/التنشيط
if (isset($_GET['action']) && isset($_GET['token'])) {
    if (verifyAdminCSRF($_GET['token'])) {
        $new_status = ($_GET['action'] == 'ban') ? 'banned' : 'active';
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $user_id);
        $stmt->execute();
        $stmt->close();
        logAdminAction('update_user_status', "تحديث حالة المستخدم #$user_id إلى: $new_status");
    }
    header("Location: admin_user_details.php?id=$user_id");
    exit();
}

// جلب بيانات المستخدم
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$user) {
    header("Location: admin_users.php");
    exit();
}

$page_title = 'ملف العميل: ' . mb_substr($user['full_name'], 0, 15);
$page_icon = 'fa-user';

// إحصائيات الطلبات
$stmt = $conn->prepare("SELECT COUNT(*) as total_orders, SUM(total_price) as total_spent FROM orders WHERE user_id = ? AND status != 'cancelled'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders_stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

include 'includes/admin_header.php';
?>

<!-- بطاقة الملف الشخصي -->
<div class="card" style="margin-bottom: 25px;">
    <div class="card-body">
        <div style="display: flex; gap: 30px; align-items: center; flex-wrap: wrap;">
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['full_name']); ?>&size=120&background=6366f1&color=fff" 
                 style="width: 100px; height: 100px; border-radius: var(--radius-lg);">
            
            <div style="flex: 1;">
                <h2 style="color: var(--text-primary); margin-bottom: 5px;">
                    <?php echo htmlspecialchars($user['full_name']); ?>
                </h2>
                <p style="color: var(--text-muted); margin-bottom: 15px;">
                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?>
                    &nbsp;&nbsp;
                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['phone']); ?>
                </p>
                
                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <?php if (($user['status'] ?? 'active') == 'active'): ?>
                        <span class="badge badge-success" style="padding: 8px 15px;">
                            <i class="fas fa-check-circle"></i> حساب نشط
                        </span>
                        <a href="?id=<?php echo $user_id; ?>&action=ban&token=<?php echo $csrf_token; ?>" 
                           class="btn btn-sm btn-danger" data-confirm="هل تريد تجميد هذا الحساب؟">
                            <i class="fas fa-ban"></i> تجميد الحساب
                        </a>
                    <?php else: ?>
                        <span class="badge badge-danger" style="padding: 8px 15px;">
                            <i class="fas fa-ban"></i> حساب محظور
                        </span>
                        <a href="?id=<?php echo $user_id; ?>&action=unban&token=<?php echo $csrf_token; ?>" 
                           class="btn btn-sm btn-success">
                            <i class="fas fa-check"></i> إعادة تنشيط
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div style="display: flex; gap: 20px; text-align: center;">
                <div style="background: var(--bg-hover); padding: 20px 30px; border-radius: var(--radius-md);">
                    <div style="font-size: 28px; font-weight: 700; color: var(--primary);">
                        <?php echo $orders_stats['total_orders'] ?? 0; ?>
                    </div>
                    <div style="color: var(--text-muted); font-size: 13px;">طلب</div>
                </div>
                <div style="background: var(--bg-hover); padding: 20px 30px; border-radius: var(--radius-md);">
                    <div style="font-size: 28px; font-weight: 700; color: var(--secondary);">
                        <?php echo formatPrice($orders_stats['total_spent'] ?? 0); ?>
                    </div>
                    <div style="color: var(--text-muted); font-size: 13px;">إجمالي المشتريات</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- سجل الطلبات -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-history"></i>
            سجل الطلبات
        </h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>رقم الطلب</th>
                        <th>التاريخ</th>
                        <th>المبلغ</th>
                        <th>الحالة</th>
                        <th>العنوان</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $orders = $stmt->get_result();
                    if ($orders && $orders->num_rows > 0):
                        while ($order = $orders->fetch_assoc()):
                            $status_badges = [
                                'pending' => ['badge-warning', 'قيد الانتظار'],
                                'shipped' => ['badge-primary', 'في الطريق'],
                                'delivered' => ['badge-success', 'تم التوصيل'],
                                'cancelled' => ['badge-danger', 'ملغي']
                            ];
                            $badge = $status_badges[$order['status']] ?? ['badge-info', $order['status']];
                    ?>
                    <tr>
                        <td style="font-weight: 600; color: var(--primary);">#<?php echo $order['id']; ?></td>
                        <td style="color: var(--text-muted);"><?php echo date('Y/m/d', strtotime($order['order_date'])); ?></td>
                        <td style="color: var(--secondary); font-weight: 600;"><?php echo formatPrice($order['total_price']); ?></td>
                        <td><span class="badge <?php echo $badge[0]; ?>"><?php echo $badge[1]; ?></span></td>
                        <td style="font-size: 13px; color: var(--text-secondary);"><?php echo htmlspecialchars(mb_substr($order['address'], 0, 40)); ?>...</td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-muted);">
                            لا توجد طلبات لهذا العميل
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
/* تحسينات التجاوب لصفحة تفاصيل المستخدم */
@media (max-width: 992px) {
    .card:first-of-type .card-body > div[style*="display: flex"][style*="gap: 30px"] {
        flex-direction: column;
        text-align: center;
    }
}

@media (max-width: 768px) {
    .admin-table td:nth-child(5),
    .admin-table th:nth-child(5) {
        display: none;
    }
    
    .card:first-of-type .card-body > div > div[style*="display: flex"][style*="gap: 20px"] {
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .card:first-of-type .card-body > div > div[style*="display: flex"][style*="gap: 20px"] > div {
        flex: 1;
        min-width: 120px;
    }
}

@media (max-width: 576px) {
    .admin-table td:nth-child(2),
    .admin-table th:nth-child(2) {
        display: none;
    }
    
    .card:first-of-type .card-body > div > div[style*="display: flex"][style*="gap: 15px"] {
        flex-direction: column;
        align-items: center;
    }
}
</style>

<?php include 'includes/admin_footer.php'; ?>

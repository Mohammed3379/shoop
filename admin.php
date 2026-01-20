<?php
include '../app/config/database.php';
include 'admin_auth.php';

// التحقق من صلاحيات الأدمن
checkAdminAuth();

// إعدادات الصفحة
$page_title = 'لوحة القيادة';
$page_icon = 'fa-chart-pie';

// 1. إحصائيات عامة
$products_count = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'] ?? 0;
$orders_count = $conn->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc()['count'] ?? 0;
$pending_orders = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'")->fetch_assoc()['count'] ?? 0;
$sales_total = $conn->query("SELECT SUM(total_price) as total FROM orders WHERE status != 'cancelled'")->fetch_assoc()['total'] ?? 0;
$users_count = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'] ?? 0;
$agents_count = $conn->query("SELECT COUNT(*) as count FROM delivery_agents")->fetch_assoc()['count'] ?? 0;

// العميل الذهبي
$top_customer = $conn->query("
    SELECT u.full_name, SUM(o.total_price) as total_spent 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    WHERE o.status != 'cancelled'
    GROUP BY o.user_id 
    ORDER BY total_spent DESC 
    LIMIT 1
")->fetch_assoc();

// حذف منتج
if (isset($_GET['delete']) && isset($_GET['token'])) {
    if (!verifyAdminCSRF($_GET['token'])) {
        die("طلب غير صالح!");
    }
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    logAdminAction('delete_product', "تم حذف المنتج رقم: $id");
    header("Location: admin.php?deleted=1");
    exit();
}

$csrf_token = getAdminCSRF();

// تضمين الهيدر
include 'includes/admin_header.php';
?>

<!-- رسالة النجاح -->
<?php if (isset($_GET['deleted'])): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i>
    تم حذف المنتج بنجاح!
</div>
<?php endif; ?>

<!-- بطاقات الإحصائيات -->
<div class="stats-grid">
    <div class="stat-card hover-lift">
        <div class="stat-header">
            <div class="stat-icon green">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-trend up">
                <i class="fas fa-arrow-up"></i>
                12%
            </div>
        </div>
        <div class="stat-value" data-count="<?php echo intval($sales_total); ?>">
            <?php echo formatPrice($sales_total); ?>
        </div>
        <div class="stat-label">إجمالي المبيعات</div>
    </div>
    
    <div class="stat-card hover-lift">
        <div class="stat-header">
            <div class="stat-icon orange">
                <i class="fas fa-shopping-bag"></i>
            </div>
            <?php if ($pending_orders > 0): ?>
            <div class="stat-trend" style="background:rgba(245,158,11,0.15);color:#f59e0b;">
                <?php echo $pending_orders; ?> جديد
            </div>
            <?php endif; ?>
        </div>
        <div class="stat-value"><?php echo $orders_count; ?></div>
        <div class="stat-label">إجمالي الطلبات</div>
    </div>
    
    <div class="stat-card hover-lift">
        <div class="stat-header">
            <div class="stat-icon purple">
                <i class="fas fa-users"></i>
            </div>
        </div>
        <div class="stat-value"><?php echo $users_count; ?></div>
        <div class="stat-label">العملاء المسجلين</div>
    </div>
    
    <div class="stat-card hover-lift">
        <div class="stat-header">
            <div class="stat-icon blue">
                <i class="fas fa-box"></i>
            </div>
        </div>
        <div class="stat-value"><?php echo $products_count; ?></div>
        <div class="stat-label">المنتجات</div>
    </div>
</div>

<!-- الصف الثاني -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-top: 25px;">
    
    <!-- أفضل العملاء -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-crown" style="color: #f59e0b;"></i>
                أفضل العملاء
            </h3>
            <a href="admin_users.php" class="btn btn-sm btn-outline">عرض الكل</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>العميل</th>
                            <th>الطلبات</th>
                            <th>الإجمالي</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql_top = "SELECT u.full_name, COUNT(o.id) as order_count, SUM(o.total_price) as total_spent 
                                    FROM users u 
                                    JOIN orders o ON u.id = o.user_id 
                                    WHERE o.status != 'cancelled'
                                    GROUP BY u.id 
                                    ORDER BY total_spent DESC 
                                    LIMIT 5";
                        $res_top = $conn->query($sql_top);
                        $rank = 1;
                        
                        if ($res_top && $res_top->num_rows > 0):
                            while ($user = $res_top->fetch_assoc()):
                                $badge_colors = ['#f59e0b', '#94a3b8', '#cd7f32'];
                                $badge_color = $badge_colors[$rank-1] ?? 'var(--text-muted)';
                        ?>
                        <tr>
                            <td>
                                <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;background:<?php echo $badge_color; ?>;color:white;font-weight:700;font-size:12px;">
                                    <?php echo $rank; ?>
                                </span>
                            </td>
                            <td style="font-weight: 600; color: var(--text-primary);">
                                <?php echo htmlspecialchars($user['full_name']); ?>
                            </td>
                            <td>
                                <span class="badge badge-info"><?php echo $user['order_count']; ?> طلب</span>
                            </td>
                            <td style="color: var(--secondary); font-weight: 700;">
                                <?php echo formatPrice($user['total_spent']); ?>
                            </td>
                        </tr>
                        <?php 
                            $rank++;
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                <i class="fas fa-inbox" style="font-size: 40px; margin-bottom: 15px; display: block;"></i>
                                لا توجد بيانات بعد
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- آخر المنتجات -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-box"></i>
                آخر المنتجات
            </h3>
            <a href="add_product.php" class="btn btn-sm btn-success">
                <i class="fas fa-plus"></i>
                إضافة
            </a>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>المنتج</th>
                            <th>السعر</th>
                            <th>الحالة</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $result = $conn->query("SELECT * FROM products ORDER BY id DESC LIMIT 5");
                        if ($result && $result->num_rows > 0):
                            while ($row = $result->fetch_assoc()):
                                $status_map = [
                                    'active' => ['badge-success', 'متوفر'],
                                    'out_of_stock' => ['badge-danger', 'نفذ'],
                                    'paused' => ['badge-warning', 'متوقف'],
                                    'hidden' => ['badge-info', 'مخفي']
                                ];
                                $st = $status_map[$row['status'] ?? 'active'] ?? ['badge-info', 'غير محدد'];
                                $final_price = $row['final_price'] ?? $row['price'];
                        ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <img src="<?php echo htmlspecialchars($row['image']); ?>" 
                                         style="width: 45px; height: 45px; border-radius: 8px; object-fit: cover; background: white;">
                                    <div>
                                        <span style="font-weight: 500; color: var(--text-primary); display: block;">
                                            <?php echo htmlspecialchars(mb_substr($row['name'], 0, 20)); ?>
                                        </span>
                                        <small style="color: var(--text-muted);">الكمية: <?php echo $row['quantity'] ?? 0; ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span style="color: var(--secondary); font-weight: 600;"><?php echo formatPrice($final_price); ?></span>
                                <?php if (($row['discount_type'] ?? 'none') != 'none' && ($row['discount_value'] ?? 0) > 0): ?>
                                    <br><small style="color: var(--text-muted); text-decoration: line-through;"><?php echo formatPrice($row['price']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?php echo $st[0]; ?>"><?php echo $st[1]; ?></span>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <a href="edit_product.php?id=<?php echo $row['id']; ?>" 
                                       class="action-btn edit" data-tooltip="تعديل">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="admin.php?delete=<?php echo $row['id']; ?>&token=<?php echo $csrf_token; ?>" 
                                       class="action-btn delete" 
                                       data-confirm="هل تريد حذف هذا المنتج؟">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                <i class="fas fa-box-open" style="font-size: 40px; margin-bottom: 15px; display: block;"></i>
                                لا توجد منتجات بعد
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- آخر الطلبات -->
<div class="card" style="margin-top: 25px;">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-clock"></i>
            آخر الطلبات
        </h3>
        <a href="admin_orders.php" class="btn btn-sm btn-primary">
            عرض الكل
            <i class="fas fa-arrow-left"></i>
        </a>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>رقم الطلب</th>
                        <th>العميل</th>
                        <th>المبلغ</th>
                        <th>الحالة</th>
                        <th>التاريخ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $orders = $conn->query("SELECT * FROM orders ORDER BY id DESC LIMIT 5");
                    if ($orders && $orders->num_rows > 0):
                        while ($order = $orders->fetch_assoc()):
                            $status_badges = [
                                'pending' => ['badge-warning', 'قيد الانتظار'],
                                'processing' => ['badge-info', 'قيد التجهيز'],
                                'shipped' => ['badge-primary', 'في الطريق'],
                                'delivered' => ['badge-success', 'تم التوصيل'],
                                'cancelled' => ['badge-danger', 'ملغي']
                            ];
                            $badge = $status_badges[$order['status']] ?? ['badge-info', $order['status']];
                    ?>
                    <tr>
                        <td style="font-weight: 600; color: var(--primary);">#<?php echo $order['id']; ?></td>
                        <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                        <td style="color: var(--secondary); font-weight: 600;">
                            <?php echo formatPrice($order['total_price']); ?>
                        </td>
                        <td>
                            <span class="badge <?php echo $badge[0]; ?>">
                                <?php echo $badge[1]; ?>
                            </span>
                        </td>
                        <td style="color: var(--text-muted);">
                            <?php echo date('Y/m/d', strtotime($order['order_date'])); ?>
                        </td>
                    </tr>
                    <?php 
                        endwhile;
                    else:
                    ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-muted);">
                            <i class="fas fa-shopping-bag" style="font-size: 40px; margin-bottom: 15px; display: block;"></i>
                            لا توجد طلبات بعد
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
/* تعديلات إضافية للصفحة الرئيسية */
@media (max-width: 1024px) {
    div[style*="grid-template-columns: 1fr 1fr"] {
        grid-template-columns: 1fr !important;
    }
}

@media (max-width: 768px) {
    /* تحسين جدول أفضل العملاء */
    .admin-table td:nth-child(3) {
        display: none;
    }
    
    /* تحسين جدول المنتجات */
    .admin-table td:nth-child(2) {
        max-width: 100px;
    }
    
    .admin-table td:nth-child(2) span[style*="font-weight: 500"] {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 80px;
        display: block;
    }
}

@media (max-width: 576px) {
    /* إخفاء عمود الحالة في الشاشات الصغيرة */
    .admin-table td:nth-child(3),
    .admin-table th:nth-child(3) {
        display: none;
    }
    
    /* تصغير الصور */
    .admin-table img[style*="width: 45px"] {
        width: 35px !important;
        height: 35px !important;
    }
    
    /* تحسين أزرار الإجراءات */
    .action-btns {
        flex-wrap: nowrap;
    }
    
    .action-btn {
        width: 28px;
        height: 28px;
        font-size: 11px;
    }
}
</style>

<?php include 'includes/admin_footer.php'; ?>

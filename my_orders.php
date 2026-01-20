<?php
include 'app/config/database.php';
include 'header.php';

// 1. التحقق الصارم من الجلسة
if (!isset($_SESSION['user_id'])) {
    // طرد المستخدم فوراً إذا لم يسجل دخوله
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// 2. كود إلغاء الطلب (مؤمن بالكامل)
if (isset($_GET['cancel_id'])) {
    $cancel_id = intval($_GET['cancel_id']);
    
    // 🔒 الحماية: نتأكد أن الطلب يخص هذا المستخدم تحديداً (AND user_id = ?)
    $stmt = $conn->prepare("SELECT status FROM orders WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $cancel_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order_data = $result->fetch_assoc();
    
    if ($order_data && $order_data['status'] == 'pending') {
        // تنفيذ الإلغاء
        $update_stmt = $conn->prepare("UPDATE orders SET status='cancelled' WHERE id = ?");
        $update_stmt->bind_param("i", $cancel_id);
        $update_stmt->execute();
        echo "<script>alert('تم إلغاء الطلب بنجاح'); window.location.href='my_orders.php';</script>";
    } else {
        echo "<script>alert('عملية غير مصرح بها أو الطلب لا يمكن إلغاؤه!');</script>";
    }
}
?>

<!-- ========== تصميم عصري لصفحة طلباتي ========== -->
<style>
/* ========== الحاوية الرئيسية ========== */
.orders-page {
    max-width: 1000px;
    margin: 0 auto;
    padding: 30px 20px;
}

/* ========== العنوان الرئيسي ========== */
.orders-header {
    text-align: center;
    margin-bottom: 40px;
    animation: fadeInDown 0.6s ease;
}

@keyframes fadeInDown {
    from { opacity: 0; transform: translateY(-30px); }
    to { opacity: 1; transform: translateY(0); }
}

.orders-header h1 {
    font-size: 32px;
    font-weight: 800;
    margin: 0 0 10px 0;
    background: linear-gradient(135deg, #6366f1, #8b5cf6, #a855f7);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    display: inline-flex;
    align-items: center;
    gap: 15px;
}

.orders-header h1 i {
    font-size: 36px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.orders-header p {
    color: #888;
    font-size: 15px;
    margin: 0;
}

/* ========== إحصائيات سريعة ========== */
.orders-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-bottom: 35px;
    animation: fadeInUp 0.6s ease 0.2s both;
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.stat-box {
    background: linear-gradient(145deg, #1e1e2e, #252538);
    border-radius: 16px;
    padding: 20px;
    text-align: center;
    border: 1px solid rgba(99, 102, 241, 0.1);
    transition: all 0.3s ease;
}

.stat-box:hover {
    transform: translateY(-5px);
    border-color: rgba(99, 102, 241, 0.3);
    box-shadow: 0 10px 30px rgba(99, 102, 241, 0.15);
}

.stat-box .stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 12px;
    font-size: 22px;
}

.stat-box.pending .stat-icon { background: rgba(251, 191, 36, 0.15); color: #fbbf24; }
.stat-box.processing .stat-icon { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
.stat-box.delivered .stat-icon { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.stat-box.cancelled .stat-icon { background: rgba(239, 68, 68, 0.15); color: #ef4444; }

.stat-box .stat-number {
    font-size: 28px;
    font-weight: 800;
    color: #fff;
    margin-bottom: 5px;
}

.stat-box .stat-label {
    font-size: 13px;
    color: #888;
}

/* ========== قائمة الطلبات ========== */
.orders-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

/* ========== بطاقة الطلب ========== */
.order-card-new {
    background: linear-gradient(145deg, #1e1e2e, #252538);
    border-radius: 20px;
    overflow: hidden;
    border: 1px solid rgba(99, 102, 241, 0.1);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    animation: fadeInUp 0.5s ease both;
}

.order-card-new:hover {
    transform: translateY(-5px);
    border-color: rgba(99, 102, 241, 0.3);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
}

/* ========== رأس البطاقة ========== */
.order-card-header {
    padding: 20px 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    background: rgba(99, 102, 241, 0.03);
}

.order-number {
    display: flex;
    align-items: center;
    gap: 12px;
}

.order-number .order-icon {
    width: 45px;
    height: 45px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    color: white;
}

.order-number h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 700;
    color: #fff;
}

.order-number .order-date {
    font-size: 13px;
    color: #888;
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 4px;
}

/* ========== حالة الطلب ========== */
.status-badge {
    padding: 8px 18px;
    border-radius: 25px;
    font-size: 13px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.status-badge.pending {
    background: linear-gradient(135deg, rgba(251, 191, 36, 0.2), rgba(245, 158, 11, 0.1));
    color: #fbbf24;
    border: 1px solid rgba(251, 191, 36, 0.3);
}

.status-badge.processing {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(37, 99, 235, 0.1));
    color: #3b82f6;
    border: 1px solid rgba(59, 130, 246, 0.3);
}

.status-badge.shipped {
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(124, 58, 237, 0.1));
    color: #8b5cf6;
    border: 1px solid rgba(139, 92, 246, 0.3);
}

.status-badge.delivered {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.1));
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.status-badge.cancelled {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(220, 38, 38, 0.1));
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

/* ========== محتوى البطاقة ========== */
.order-card-body {
    padding: 25px;
}

.order-details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 12px;
}

.detail-item .detail-icon {
    width: 40px;
    height: 40px;
    background: rgba(99, 102, 241, 0.1);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #8b5cf6;
    font-size: 16px;
}

.detail-item .detail-text {
    flex: 1;
}

.detail-item .detail-label {
    font-size: 12px;
    color: #888;
    margin-bottom: 3px;
}

.detail-item .detail-value {
    font-size: 14px;
    font-weight: 600;
    color: #fff;
}

/* ========== المنتجات ========== */
.order-products {
    background: rgba(0, 0, 0, 0.2);
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 20px;
}

.order-products-title {
    font-size: 13px;
    color: #888;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.order-products-text {
    font-size: 14px;
    color: #ccc;
    line-height: 1.6;
}

/* ========== تذييل البطاقة ========== */
.order-card-footer {
    padding: 20px 25px;
    background: rgba(0, 0, 0, 0.15);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.order-total {
    display: flex;
    flex-direction: column;
}

.order-total .total-label {
    font-size: 12px;
    color: #888;
}

.order-total .total-amount {
    font-size: 24px;
    font-weight: 800;
    background: linear-gradient(135deg, #10b981, #34d399);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.order-actions-new {
    display: flex;
    gap: 12px;
}

.btn-action {
    padding: 12px 24px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    cursor: pointer;
    border: none;
}

.btn-track-new {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
}

.btn-track-new:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(99, 102, 241, 0.5);
    color: white;
}

.btn-cancel-new {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.btn-cancel-new:hover {
    background: #ef4444;
    color: white;
    transform: translateY(-2px);
}

/* ========== حالة فارغة ========== */
.empty-orders {
    text-align: center;
    padding: 80px 20px;
    animation: fadeInUp 0.6s ease;
}

.empty-orders .empty-icon {
    width: 120px;
    height: 120px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.05));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 25px;
    font-size: 50px;
    color: #6366f1;
}

.empty-orders h3 {
    font-size: 24px;
    font-weight: 700;
    color: #fff;
    margin: 0 0 10px 0;
}

.empty-orders p {
    color: #888;
    font-size: 15px;
    margin: 0 0 25px 0;
}

.btn-shop-now {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 14px 30px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    text-decoration: none;
    border-radius: 12px;
    font-weight: 600;
    box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
    transition: all 0.3s ease;
}

.btn-shop-now:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 35px rgba(99, 102, 241, 0.5);
    color: white;
}

/* ========== Responsive ========== */
@media (max-width: 600px) {
    .orders-header h1 { font-size: 24px; }
    .order-card-header { flex-direction: column; gap: 15px; text-align: center; }
    .order-card-footer { flex-direction: column; text-align: center; }
    .order-actions-new { width: 100%; justify-content: center; }
}
</style>

<div class="orders-page">
    <!-- العنوان -->
    <div class="orders-header">
        <h1><i class="fas fa-shopping-bag"></i> طلباتي</h1>
        <p>تتبع جميع طلباتك ومشترياتك من مكان واحد</p>
    </div>

    <?php
    // ترجمة طرق الدفع والشحن
    $payment_methods_text = [
        'cod' => 'الدفع عند الاستلام',
        'cash_on_delivery' => 'الدفع عند الاستلام',
        'credit_card' => 'بطاقة ائتمانية',
        'bank_transfer' => 'تحويل بنكي',
        'wallet' => 'محفظة إلكترونية'
    ];
    $shipping_methods_text = [
        'standard' => 'شحن عادي',
        'express' => 'شحن سريع',
        'free' => 'شحن مجاني',
        'pickup' => 'استلام من المتجر'
    ];
    
    $status_text = [
        'pending' => 'قيد الانتظار',
        'processing' => 'قيد المعالجة',
        'shipped' => 'تم الشحن',
        'delivered' => 'تم التوصيل',
        'cancelled' => 'ملغي'
    ];
    
    $status_icons = [
        'pending' => 'fa-clock',
        'processing' => 'fa-cog fa-spin',
        'shipped' => 'fa-truck',
        'delivered' => 'fa-check-circle',
        'cancelled' => 'fa-times-circle'
    ];
    
    // جلب الطلبات
    $stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // حساب الإحصائيات
    $stats = ['pending' => 0, 'processing' => 0, 'delivered' => 0, 'cancelled' => 0];
    $orders = [];
    while($row = $result->fetch_assoc()) {
        $orders[] = $row;
        $s = $row['status'];
        if (isset($stats[$s])) $stats[$s]++;
        elseif ($s == 'shipped') $stats['processing']++;
    }
    ?>

    <!-- الإحصائيات -->
    <?php if (count($orders) > 0): ?>
    <div class="orders-stats">
        <div class="stat-box pending">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-number"><?php echo $stats['pending']; ?></div>
            <div class="stat-label">قيد الانتظار</div>
        </div>
        <div class="stat-box processing">
            <div class="stat-icon"><i class="fas fa-cog"></i></div>
            <div class="stat-number"><?php echo $stats['processing']; ?></div>
            <div class="stat-label">قيد المعالجة</div>
        </div>
        <div class="stat-box delivered">
            <div class="stat-icon"><i class="fas fa-check"></i></div>
            <div class="stat-number"><?php echo $stats['delivered']; ?></div>
            <div class="stat-label">تم التوصيل</div>
        </div>
        <div class="stat-box cancelled">
            <div class="stat-icon"><i class="fas fa-times"></i></div>
            <div class="stat-number"><?php echo $stats['cancelled']; ?></div>
            <div class="stat-label">ملغي</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- قائمة الطلبات -->
    <div class="orders-list">
        <?php if (count($orders) > 0): ?>
            <?php foreach($orders as $index => $row): 
                $status = htmlspecialchars($row['status']);
                $payment_text = $payment_methods_text[$row['payment_method'] ?? 'cod'] ?? 'الدفع عند الاستلام';
                $shipping_text = $shipping_methods_text[$row['shipping_method'] ?? 'standard'] ?? 'شحن عادي';
                $order_date = $row['created_at'] ?? $row['order_date'] ?? '';
                $status_label = $status_text[$status] ?? $status;
                $status_icon = $status_icons[$status] ?? 'fa-info-circle';
            ?>
            <div class="order-card-new" style="animation-delay: <?php echo $index * 0.1; ?>s">
                <!-- رأس البطاقة -->
                <div class="order-card-header">
                    <div class="order-number">
                        <div class="order-icon"><i class="fas fa-receipt"></i></div>
                        <div>
                            <h3>طلب #<?php echo $row['id']; ?></h3>
                            <div class="order-date">
                                <i class="far fa-calendar-alt"></i>
                                <?php echo date('Y/m/d - h:i A', strtotime($order_date)); ?>
                            </div>
                        </div>
                    </div>
                    <span class="status-badge <?php echo $status; ?>">
                        <i class="fas <?php echo $status_icon; ?>"></i>
                        <?php echo $status_label; ?>
                    </span>
                </div>
                
                <!-- محتوى البطاقة -->
                <div class="order-card-body">
                    <div class="order-details-grid">
                        <div class="detail-item">
                            <div class="detail-icon"><i class="fas fa-truck"></i></div>
                            <div class="detail-text">
                                <div class="detail-label">طريقة الشحن</div>
                                <div class="detail-value"><?php echo $shipping_text; ?></div>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-icon"><i class="fas fa-credit-card"></i></div>
                            <div class="detail-text">
                                <div class="detail-label">طريقة الدفع</div>
                                <div class="detail-value"><?php echo $payment_text; ?></div>
                            </div>
                        </div>
                        <?php if (!empty($row['phone'])): ?>
                        <div class="detail-item">
                            <div class="detail-icon"><i class="fas fa-phone"></i></div>
                            <div class="detail-text">
                                <div class="detail-label">رقم الهاتف</div>
                                <div class="detail-value"><?php echo htmlspecialchars($row['phone']); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="order-products">
                        <div class="order-products-title">
                            <i class="fas fa-box"></i> المنتجات
                        </div>
                        <div class="order-products-text">
                            <?php echo htmlspecialchars(mb_substr($row['products'], 0, 150)); ?>...
                        </div>
                    </div>
                </div>
                
                <!-- تذييل البطاقة -->
                <div class="order-card-footer">
                    <div class="order-total">
                        <span class="total-label">إجمالي الطلب</span>
                        <span class="total-amount"><?php echo formatPrice($row['total_price']); ?></span>
                    </div>
                    <div class="order-actions-new">
                        <?php if($status != 'cancelled'): ?>
                        <a href="track.php?order_id=<?php echo $row['id']; ?>" class="btn-action btn-track-new">
                            <i class="fas fa-map-marker-alt"></i> تتبع الطلب
                        </a>
                        <?php endif; ?>
                        <?php if($status == 'pending'): ?>
                        <a href="my_orders.php?cancel_id=<?php echo $row['id']; ?>" 
                           class="btn-action btn-cancel-new" 
                           onclick="return confirm('هل أنت متأكد من إلغاء هذا الطلب؟')">
                            <i class="fas fa-times"></i> إلغاء
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-orders">
                <div class="empty-icon">
                    <i class="fas fa-shopping-bag"></i>
                </div>
                <h3>لا توجد طلبات بعد</h3>
                <p>ابدأ التسوق الآن واستمتع بأفضل المنتجات والعروض</p>
                <a href="index.php" class="btn-shop-now">
                    <i class="fas fa-store"></i> تسوق الآن
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
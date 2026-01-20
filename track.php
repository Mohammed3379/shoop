<?php 
include 'header.php'; 
include 'app/config/database.php';

// 1. حماية الصفحة (للمسجلين فقط)
if (!isset($_SESSION['user_id'])) {
    echo "<div style='padding:50px; text-align:center;'>
            <i class='fas fa-lock' style='font-size:50px; color:#dc3545; margin-bottom:20px;'></i><br>
            يجب تسجيل الدخول لعرض طلباتك
            <br><br>
            <a href='login.php' style='color:#ffc107'>تسجيل الدخول</a>
          </div>";
    include 'footer.php';
    exit();
}

$user_id = $_SESSION['user_id'];
$specific_order = null; // لتخزين بيانات الطلب المحدد
$show_list = true;      // افتراضياً نعرض القائمة

// 2. هل العميل اختار طلباً محدداً؟
if (isset($_GET['order_id'])) {
    $id = intval($_GET['order_id']);
    
    // جلب الطلب بشرط أنه يخص هذا المستخدم
    $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $specific_order = $result->fetch_assoc();
        $show_list = false; // نخفي القائمة ونعرض التفاصيل
    } else {
        echo "<script>alert('الطلب غير موجود أو لا تملك صلاحية عرضه'); window.location.href='track.php';</script>";
    }
}
?>



<div class="track-container">

    <?php if ($show_list): ?>
        <h1 style="color:white; margin-bottom:30px;"><i class="fas fa-shipping-fast"></i> تتبع طلباتك</h1>
        
        <?php
        $sql = "SELECT * FROM orders WHERE user_id = $user_id ORDER BY id DESC";
        $result = $conn->query($sql);

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // تحديد النصوص والألوان
                $st = $row['status'];
                $txt = ['pending'=>'قيد المراجعة','shipped'=>'في الطريق','delivered'=>'تم التوصيل','cancelled'=>'ملغي'][$st] ?? $st;
                $cls = "s-" . $st;
                ?>
                <div class="order-list-item">
                    <div class="order-main-info">
                        <h3>طلب رقم #<?php echo $row['id']; ?></h3>
                        <div class="order-sub-info">
                            <span class="status-badge <?php echo $cls; ?>"><?php echo $txt; ?></span>
                            <span style="margin-right:10px;"><i class="far fa-clock"></i> <?php echo date('Y-m-d', strtotime($row['order_date'])); ?></span>
                        </div>
                    </div>
                    <a href="track.php?order_id=<?php echo $row['id']; ?>" class="btn-view">تتبع <i class="fas fa-chevron-left"></i></a>
                </div>
                <?php
            }
        } else {
            echo "<p style='color:#888; text-align:center;'>ليس لديك طلبات نشطة حالياً.</p>";
        }
        ?>

    <?php else: ?>
        <a href="track.php" class="btn-back"><i class="fas fa-arrow-right"></i> العودة للقائمة</a>
        
        <h2 style="color:white; text-align:center;">حالة الطلب #<?php echo $specific_order['id']; ?></h2>

        <?php 
            $s = $specific_order['status'];
            // منطق الستيبس (Steps Logic)
            $step1 = ($s=='pending'||$s=='shipped'||$s=='delivered') ? 'active' : '';
            $step2 = ($s=='shipped'||$s=='delivered') ? 'active' : '';
            $step3 = ($s=='delivered') ? 'active' : '';
            
            // معالجة حالة الإلغاء
            if($s == 'cancelled') {
                echo "<div style='background:#dc3545; color:white; padding:15px; text-align:center; border-radius:10px; margin:20px 0;'>⛔ هذا الطلب تم إلغاؤه</div>";
                $step1=$step2=$step3=''; // تصفير الخطوات
            }
        ?>

        <?php if($s != 'cancelled'): ?>
        <div class="stepper-wrapper">
            <div class="stepper-item <?php echo $step1; ?> <?php echo ($step2=='active')?'completed':''; ?>">
                <div class="step-counter"><i class="fas fa-clipboard-check"></i></div>
                <div class="step-name">مراجعة</div>
            </div>
            <div class="stepper-item <?php echo $step2; ?> <?php echo ($step3=='active')?'completed':''; ?>">
                <div class="step-counter"><i class="fas fa-shipping-fast"></i></div>
                <div class="step-name">شحن</div>
            </div>
            <div class="stepper-item <?php echo $step3; ?>">
                <div class="step-counter"><i class="fas fa-home"></i></div>
                <div class="step-name">وصول</div>
            </div>
        </div>
        <?php endif; ?>

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
        $payment_text = $payment_methods_text[$specific_order['payment_method'] ?? 'cod'] ?? 'الدفع عند الاستلام';
        $shipping_text = $shipping_methods_text[$specific_order['shipping_method'] ?? 'standard'] ?? 'شحن عادي';
        ?>
        <div class="details-card">
            <h3 style="color:#ffc107; margin-top:0;">تفاصيل الشحنة</h3>
            <p style="color:#ccc; line-height:1.8;">
                <strong>المنتجات:</strong> <?php echo htmlspecialchars($specific_order['products']); ?><br>
                <strong>العنوان:</strong> <?php echo htmlspecialchars($specific_order['address']); ?><br>
                <strong>طريقة الشحن:</strong> <span style="color:#17a2b8;"><i class="fas fa-truck"></i> <?php echo $shipping_text; ?></span><br>
                <strong>طريقة الدفع:</strong> <span style="color:#ffc107;"><i class="fas fa-credit-card"></i> <?php echo $payment_text; ?></span>
            </p>
            
            <!-- تفاصيل المبالغ -->
            <div style="background:rgba(255,255,255,0.05); padding:15px; border-radius:8px; margin-top:15px;">
                <?php if (($specific_order['subtotal'] ?? 0) > 0): ?>
                <div style="display:flex; justify-content:space-between; margin-bottom:8px; color:#aaa;">
                    <span>المجموع الفرعي:</span>
                    <span><?php echo formatPrice($specific_order['subtotal']); ?></span>
                </div>
                <?php endif; ?>
                
                <div style="display:flex; justify-content:space-between; margin-bottom:8px; color:#aaa;">
                    <span>الشحن (<?php echo $shipping_text; ?>):</span>
                    <?php if (($specific_order['shipping_cost'] ?? 0) > 0): ?>
                    <span><?php echo formatPrice($specific_order['shipping_cost']); ?></span>
                    <?php else: ?>
                    <span style="color:#28a745;">مجاني</span>
                    <?php endif; ?>
                </div>
                
                <?php if (($specific_order['payment_fee'] ?? 0) > 0): ?>
                <div style="display:flex; justify-content:space-between; margin-bottom:8px; color:#aaa;">
                    <span>رسوم الدفع:</span>
                    <span><?php echo formatPrice($specific_order['payment_fee']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (($specific_order['tax'] ?? 0) > 0): ?>
                <div style="display:flex; justify-content:space-between; margin-bottom:8px; color:#aaa;">
                    <span>الضريبة:</span>
                    <span><?php echo formatPrice($specific_order['tax']); ?></span>
                </div>
                <?php endif; ?>
                
                <div style="display:flex; justify-content:space-between; padding-top:10px; border-top:1px solid #444; margin-top:10px;">
                    <strong style="color:#fff;">الإجمالي:</strong>
                    <strong style="color:#28a745; font-size:18px;"><?php echo formatPrice($specific_order['total_price']); ?></strong>
                </div>
            </div>
            
            <?php if($s == 'shipped'): ?>
                <div style="background:rgba(23, 162, 184, 0.1); color:#17a2b8; padding:10px; border-radius:5px; margin-top:15px; text-align:center;">
                    <i class="fas fa-motorcycle"></i> الطلب خرج للتوصيل.. يرجى انتظار اتصال المندوب.
                </div>
            <?php endif; ?>
        </div>

    <?php endif; ?>

</div>

<?php include 'footer.php'; ?>
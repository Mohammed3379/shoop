<?php
include '../app/config/database.php';

if (!isset($_GET['id'])) die("رقم الفاتورة غير موجود");
$id = intval($_GET['id']);

$order = $conn->query("SELECT * FROM orders WHERE id = $id")->fetch_assoc();
if (!$order) die("الطلب غير موجود");

$date = date('Y/m/d', strtotime($order['created_at'] ?? $order['order_date'] ?? 'now'));
$status_text = [
    'pending' => 'قيد الانتظار',
    'processing' => 'قيد التجهيز',
    'shipped' => 'في الطريق',
    'delivered' => 'تم التوصيل',
    'cancelled' => 'ملغي'
][$order['status']] ?? $order['status'];

// ترجمة طرق الدفع
$payment_methods_text = [
    'cod' => 'الدفع عند الاستلام',
    'cash_on_delivery' => 'الدفع عند الاستلام',
    'credit_card' => 'بطاقة ائتمانية',
    'bank_transfer' => 'تحويل بنكي',
    'wallet' => 'محفظة إلكترونية'
];
$payment_text = $payment_methods_text[$order['payment_method'] ?? 'cod'] ?? ($order['payment_method'] ?? 'الدفع عند الاستلام');

// ترجمة طرق الشحن
$shipping_methods_text = [
    'standard' => 'شحن عادي',
    'express' => 'شحن سريع',
    'free' => 'شحن مجاني',
    'pickup' => 'استلام من المتجر'
];
$shipping_text = $shipping_methods_text[$order['shipping_method'] ?? 'standard'] ?? ($order['shipping_method'] ?? 'شحن عادي');

// القيم المالية
$subtotal = $order['subtotal'] ?? 0;
$shipping_cost = $order['shipping_cost'] ?? 0;
$payment_fee = $order['payment_fee'] ?? 0;
$tax = $order['tax'] ?? 0;
$total = $order['total_price'] ?? 0;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>فاتورة #<?php echo $id; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            direction: rtl;
        }
        
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .invoice-header {
            background: linear-gradient(135deg, #6366f1, #818cf8);
            color: white;
            padding: 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .invoice-logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .invoice-logo i {
            font-size: 40px;
        }
        
        .invoice-logo h1 {
            font-size: 28px;
            font-weight: 700;
        }
        
        .invoice-logo span {
            font-size: 14px;
            opacity: 0.8;
        }
        
        .invoice-number {
            text-align: left;
        }
        
        .invoice-number h2 {
            font-size: 14px;
            opacity: 0.8;
            margin-bottom: 5px;
        }
        
        .invoice-number span {
            font-size: 32px;
            font-weight: 700;
        }
        
        .invoice-body {
            padding: 40px;
        }
        
        .invoice-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 40px;
        }
        
        .info-section h3 {
            font-size: 12px;
            text-transform: uppercase;
            color: #888;
            margin-bottom: 15px;
            letter-spacing: 1px;
        }
        
        .info-section p {
            color: #333;
            line-height: 1.8;
            font-size: 14px;
        }
        
        .info-section strong {
            color: #111;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            margin-top: 10px;
        }
        
        .status-delivered { background: #d1fae5; color: #059669; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-shipped { background: #dbeafe; color: #2563eb; }
        .status-cancelled { background: #fee2e2; color: #dc2626; }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        .items-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: right;
            font-size: 12px;
            text-transform: uppercase;
            color: #666;
            border-bottom: 2px solid #eee;
        }
        
        .items-table td {
            padding: 18px 15px;
            border-bottom: 1px solid #eee;
            color: #333;
        }
        
        .items-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .total-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .total-section span {
            font-size: 16px;
            color: #666;
        }
        
        .total-section strong {
            font-size: 32px;
            color: #6366f1;
        }
        
        .invoice-footer {
            text-align: center;
            padding: 30px;
            background: #f8f9fa;
            border-top: 1px solid #eee;
        }
        
        .invoice-footer p {
            color: #888;
            font-size: 13px;
            margin-bottom: 5px;
        }
        
        .print-btn {
            position: fixed;
            bottom: 30px;
            left: 30px;
            background: linear-gradient(135deg, #6366f1, #818cf8);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.4);
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
        }
        
        .print-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(99, 102, 241, 0.5);
        }
        
        @media print {
            body { background: white; padding: 0; }
            .invoice-container { box-shadow: none; }
            .print-btn { display: none; }
        }
        
        @media (max-width: 600px) {
            .invoice-header { flex-direction: column; gap: 20px; text-align: center; }
            .invoice-info { grid-template-columns: 1fr; }
            .invoice-number { text-align: center; }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="invoice-header">
            <div class="invoice-logo">
                <i class="fas fa-store"></i>
                <div>
                    <h1>مشترياتي</h1>
                    <span>متجرك الإلكتروني المفضل</span>
                </div>
            </div>
            <div class="invoice-number">
                <h2>رقم الفاتورة</h2>
                <span>#<?php echo $id; ?></span>
            </div>
        </div>
        
        <div class="invoice-body">
            <div class="invoice-info">
                <div class="info-section">
                    <h3>معلومات العميل</h3>
                    <p>
                        <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong><br>
                        <i class="fas fa-phone" style="color:#888; font-size:12px;"></i> <?php echo htmlspecialchars($order['phone']); ?><br>
                        <i class="fas fa-map-marker-alt" style="color:#888; font-size:12px;"></i> <?php echo htmlspecialchars($order['address']); ?>
                    </p>
                </div>
                
                <div class="info-section">
                    <h3>تفاصيل الطلب</h3>
                    <p>
                        <strong>التاريخ:</strong> <?php echo $date; ?><br>
                        <strong>طريقة الدفع:</strong> <?php echo $payment_text; ?><br>
                        <strong>طريقة الشحن:</strong> <?php echo $shipping_text; ?>
                    </p>
                    <span class="status-badge status-<?php echo $order['status']; ?>">
                        <?php echo $status_text; ?>
                    </span>
                </div>
            </div>
            
            <table class="items-table">
                <thead>
                    <tr>
                        <th>المنتج</th>
                        <th style="text-align: left;">المبلغ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $products = explode(", ", $order['products']);
                    foreach ($products as $prod):
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($prod); ?></td>
                        <td style="text-align: left; color: #888;">-</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- تفاصيل المبالغ -->
            <div class="totals-breakdown" style="margin-bottom: 20px;">
                <?php if ($subtotal > 0): ?>
                <div class="total-row" style="display: flex; justify-content: space-between; padding: 10px 15px; border-bottom: 1px solid #eee;">
                    <span style="color: #666;">المجموع الفرعي:</span>
                    <span style="color: #333;"><?php echo formatPrice($subtotal); ?></span>
                </div>
                <?php endif; ?>
                
                <div class="total-row" style="display: flex; justify-content: space-between; padding: 10px 15px; border-bottom: 1px solid #eee;">
                    <span style="color: #666;">الشحن (<?php echo $shipping_text; ?>):</span>
                    <?php if ($shipping_cost > 0): ?>
                    <span style="color: #333;"><?php echo formatPrice($shipping_cost); ?></span>
                    <?php else: ?>
                    <span style="color: #059669; font-weight: 600;">مجاني</span>
                    <?php endif; ?>
                </div>
                
                <?php if ($payment_fee > 0): ?>
                <div class="total-row" style="display: flex; justify-content: space-between; padding: 10px 15px; border-bottom: 1px solid #eee;">
                    <span style="color: #666;">رسوم الدفع:</span>
                    <span style="color: #333;"><?php echo formatPrice($payment_fee); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($tax > 0): ?>
                <div class="total-row" style="display: flex; justify-content: space-between; padding: 10px 15px; border-bottom: 1px solid #eee;">
                    <span style="color: #666;">الضريبة:</span>
                    <span style="color: #333;"><?php echo formatPrice($tax); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="total-section">
                <span>الإجمالي النهائي</span>
                <strong><?php echo formatPrice($total); ?></strong>
            </div>
        </div>
        
        <div class="invoice-footer">
            <p>شكراً لتعاملكم معنا!</p>
            <p>للاستفسارات: support@myshop.com | 0555555555</p>
        </div>
    </div>
    
    <button onclick="window.print()" class="print-btn">
        <i class="fas fa-print"></i>
        طباعة الفاتورة
    </button>
</body>
</html>

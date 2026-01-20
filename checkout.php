<?php 
include 'app/config/database.php';
include 'app/config/payment_shipping.php';
include 'header.php'; 

if (!isset($_SESSION['user_id'])) {
    echo "<script>window.location.href = 'login.php';</script>";
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$user_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_data = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();
$payment_methods = getActivePaymentMethods($conn);
$shipping_methods = getActiveShippingMethods($conn);
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<section class="checkout-simple">
    <!-- الفاتورة الثابتة في الأعلى -->
    <div class="invoice-card">
        <div class="invoice-header-bar">
            <div class="invoice-title">
                <i class="fas fa-receipt"></i>
                <span>فاتورة طلبك</span>
            </div>
            <div class="invoice-total-badge">
                <span>الإجمالي:</span>
                <strong id="checkout-total">0.00 <?php echo getCurrencySymbol(); ?></strong>
            </div>
        </div>
        
        <!-- المنتجات -->
        <div class="invoice-products" id="checkout-items"></div>
        
        <!-- تفاصيل الأسعار -->
        <div class="invoice-details">
            <div class="detail-row">
                <span>المجموع الفرعي</span>
                <span id="checkout-subtotal">0.00 <?php echo getCurrencySymbol(); ?></span>
            </div>
            <div class="detail-row" id="shipping-row">
                <span>الشحن</span>
                <span id="checkout-shipping">0.00 <?php echo getCurrencySymbol(); ?></span>
            </div>
            <div class="detail-row" id="fee-row" style="display:none;">
                <span>رسوم الدفع</span>
                <span id="checkout-fee">0.00 <?php echo getCurrencySymbol(); ?></span>
            </div>
            <div class="detail-row">
                <span>الضريبة (<?php echo (getTaxRate() * 100); ?>%)</span>
                <span id="checkout-tax">0.00 <?php echo getCurrencySymbol(); ?></span>
            </div>
        </div>
    </div>

    <!-- خيارات الطلب -->
    <div class="checkout-options">
        
        <!-- الموقع -->
        <div class="option-section">
            <div class="option-header">
                <i class="fas fa-map-marker-alt"></i>
                <span>موقع التوصيل</span>
                <span class="status-badge" id="location-badge">مطلوب</span>
            </div>
            <div class="option-content">
                <button type="button" class="locate-btn" onclick="getMyLocation()">
                    <i class="fas fa-location-arrow"></i> تحديد موقعي
                </button>
                <div id="map"></div>
                <div class="address-box">
                    <i class="fas fa-home"></i>
                    <input type="text" id="address" placeholder="العنوان سيظهر هنا..." readonly>
                </div>
            </div>
        </div>
        
        <!-- طريقة الشحن -->
        <div class="option-section">
            <div class="option-header">
                <i class="fas fa-truck"></i>
                <span>طريقة الشحن</span>
            </div>
            <div class="option-content">
                <div class="methods-list">
                    <?php if (!empty($shipping_methods)): ?>
                        <?php foreach ($shipping_methods as $i => $m): ?>
                        <label class="method-option <?php echo $i === 0 ? 'selected' : ''; ?>">
                            <input type="radio" name="shipping" value="<?php echo $m['code']; ?>" 
                                   data-cost="<?php echo $m['cost']; ?>"
                                   data-free-min="<?php echo $m['free_shipping_min']; ?>"
                                   data-type="<?php echo $m['type']; ?>"
                                   data-name="<?php echo $m['name_ar']; ?>"
                                   <?php echo $i === 0 ? 'checked' : ''; ?>
                                   onchange="updateTotals()">
                            <div class="method-icon"><i class="fas <?php echo $m['icon']; ?>"></i></div>
                            <div class="method-text">
                                <strong><?php echo $m['name_ar']; ?></strong>
                                <small><?php echo $m['estimated_days_min']; ?>-<?php echo $m['estimated_days_max']; ?> أيام</small>
                            </div>
                            <div class="method-price">
                                <?php if ($m['type'] === 'free'): ?>
                                    <span class="free">مجاني</span>
                                <?php else: ?>
                                    <?php echo number_format($m['cost'], 2); ?> <?php echo getCurrencySymbol(); ?>
                                <?php endif; ?>
                            </div>
                            <div class="check-mark"><i class="fas fa-check"></i></div>
                        </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- طريقة الدفع -->
        <div class="option-section">
            <div class="option-header">
                <i class="fas fa-credit-card"></i>
                <span>طريقة الدفع</span>
            </div>
            <div class="option-content">
                <div class="methods-list">
                    <?php if (!empty($payment_methods)): ?>
                        <?php foreach ($payment_methods as $i => $m): ?>
                        <label class="method-option <?php echo $i === 0 ? 'selected' : ''; ?>">
                            <input type="radio" name="payment" value="<?php echo $m['code']; ?>"
                                   data-fee-type="<?php echo $m['fee_type']; ?>"
                                   data-fee-amount="<?php echo $m['fee_amount']; ?>"
                                   data-name="<?php echo $m['name_ar']; ?>"
                                   <?php echo $i === 0 ? 'checked' : ''; ?>
                                   onchange="updateTotals()">
                            <div class="method-icon"><i class="fas <?php echo $m['icon']; ?>"></i></div>
                            <div class="method-text">
                                <strong><?php echo $m['name_ar']; ?></strong>
                                <small><?php echo $m['description']; ?></small>
                            </div>
                            <?php if ($m['fee_type'] !== 'none' && $m['fee_amount'] > 0): ?>
                            <div class="method-fee">+<?php echo $m['fee_amount']; ?><?php echo $m['fee_type'] === 'percentage' ? '%' : ''; ?></div>
                            <?php endif; ?>
                            <div class="check-mark"><i class="fas fa-check"></i></div>
                        </label>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <label class="method-option selected">
                            <input type="radio" name="payment" value="cod" checked data-name="الدفع عند الاستلام">
                            <div class="method-icon"><i class="fas fa-money-bill-wave"></i></div>
                            <div class="method-text"><strong>الدفع عند الاستلام</strong></div>
                            <div class="check-mark"><i class="fas fa-check"></i></div>
                        </label>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- زر تأكيد الطلب -->
    <button class="confirm-order-btn" onclick="submitOrder()">
        <i class="fas fa-lock"></i>
        <span>تأكيد الطلب</span>
        <span class="btn-total" id="btn-total">0.00 <?php echo getCurrencySymbol(); ?></span>
    </button>
    
    <!-- ضمانات -->
    <div class="guarantees-bar">
        <span><i class="fas fa-shield-alt"></i> دفع آمن</span>
        <span><i class="fas fa-undo"></i> إرجاع مجاني</span>
        <span><i class="fas fa-truck"></i> توصيل سريع</span>
    </div>
    
    <input type="hidden" id="full_name" value="<?php echo htmlspecialchars($user_data['full_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" id="phone" value="<?php echo htmlspecialchars($user_data['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" id="lat">
    <input type="hidden" id="lng">
</section>

<!-- Modal النجاح -->
<div id="success-modal" class="modal">
    <div class="modal-box">
        <div class="success-icon"><i class="fas fa-check"></i></div>
        <h2>تم إرسال طلبك! 🎉</h2>
        <p>رقم الطلب: <strong id="order-id">#--</strong></p>
        <div class="modal-btns">
            <button onclick="location.href='my_orders.php'" class="btn-primary"><i class="fas fa-list"></i> طلباتي</button>
            <button onclick="location.href='index.php'" class="btn-secondary"><i class="fas fa-home"></i> الرئيسية</button>
        </div>
    </div>
</div>

<style>
.checkout-simple { max-width: 500px; margin: 0 auto; padding: 15px; }

/* الفاتورة */
.invoice-card {
    background: linear-gradient(145deg, #1e1e2e, #252538);
    border-radius: 20px;
    overflow: hidden;
    border: 1px solid rgba(99, 102, 241, 0.15);
    margin-bottom: 15px;
}

.invoice-header-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 18px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(139, 92, 246, 0.08));
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
}

.invoice-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 16px;
    font-weight: 700;
    color: #fff;
}

.invoice-title i { color: #8b5cf6; }

.invoice-total-badge {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 15px;
    background: linear-gradient(135deg, #10b981, #059669);
    border-radius: 25px;
    font-size: 13px;
    color: white;
}

.invoice-total-badge strong { font-size: 16px; }

.invoice-products {
    max-height: 180px;
    overflow-y: auto;
    padding: 12px;
}

.invoice-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    background: rgba(99, 102, 241, 0.05);
    border-radius: 12px;
    margin-bottom: 8px;
}

.invoice-item img {
    width: 45px;
    height: 45px;
    border-radius: 8px;
    object-fit: cover;
    background: rgba(99, 102, 241, 0.1);
}

.invoice-item img[src=""], .invoice-item img:not([src]) {
    display: none;
}

.item-icon {
    width: 45px;
    height: 45px;
    border-radius: 8px;
    background: rgba(99, 102, 241, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #8b5cf6;
    font-size: 18px;
}

.invoice-item-info { flex: 1; }
.invoice-item-info h4 { margin: 0; font-size: 13px; color: #fff; }
.invoice-item-info span { font-size: 11px; color: #888; }
.invoice-item-price { font-size: 13px; font-weight: 700; color: #10b981; }

.invoice-details {
    padding: 12px 15px;
    border-top: 1px dashed rgba(99, 102, 241, 0.15);
}

.detail-row {
    display: flex;
    justify-content: space-between;
    padding: 6px 0;
    font-size: 13px;
    color: #aaa;
}

.detail-row span:last-child { color: #fff; font-weight: 500; }

/* الخيارات */
.checkout-options { display: flex; flex-direction: column; gap: 12px; margin-bottom: 15px; }

.option-section {
    background: linear-gradient(145deg, #1e1e2e, #252538);
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid rgba(99, 102, 241, 0.1);
}

.option-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 16px;
    background: rgba(99, 102, 241, 0.05);
    border-bottom: 1px solid rgba(99, 102, 241, 0.08);
    font-size: 14px;
    font-weight: 600;
    color: #fff;
}

.option-header i { color: #8b5cf6; font-size: 16px; }

.status-badge {
    margin-right: auto;
    font-size: 10px;
    padding: 4px 10px;
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
    border-radius: 12px;
}

.status-badge.done {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}

.option-content { padding: 14px; }

/* زر الموقع */
.locate-btn {
    width: 100%;
    padding: 12px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-bottom: 12px;
    transition: all 0.3s;
}

.locate-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4); }
.locate-btn.success { background: linear-gradient(135deg, #10b981, #059669); }

#map {
    height: 150px;
    border-radius: 12px;
    border: 2px solid rgba(99, 102, 241, 0.15);
    margin-bottom: 10px;
}

.address-box {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 10px;
}

.address-box i { color: #8b5cf6; }
.address-box input {
    flex: 1;
    background: transparent;
    border: none;
    color: #ccc;
    font-size: 13px;
}

/* طرق الشحن والدفع */
.methods-list { display: flex; flex-direction: column; gap: 8px; }

.method-option {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px;
    background: rgba(99, 102, 241, 0.03);
    border: 2px solid rgba(99, 102, 241, 0.1);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s;
}

.method-option:hover { border-color: rgba(99, 102, 241, 0.3); }
.method-option:has(input:checked) {
    border-color: #8b5cf6;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.05));
}

.method-option input { display: none; }

.method-icon {
    width: 40px;
    height: 40px;
    background: rgba(139, 92, 246, 0.1);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #8b5cf6;
    font-size: 16px;
}

.method-text { flex: 1; }
.method-text strong { display: block; font-size: 13px; color: #fff; }
.method-text small { font-size: 11px; color: #888; }

.method-price { font-size: 13px; font-weight: 600; color: #fff; }
.method-price .free { color: #10b981; }

.method-fee {
    font-size: 11px;
    color: #f59e0b;
    padding: 4px 8px;
    background: rgba(245, 158, 11, 0.15);
    border-radius: 10px;
}

.check-mark {
    width: 22px;
    height: 22px;
    border: 2px solid rgba(99, 102, 241, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    color: transparent;
}

.method-option:has(input:checked) .check-mark {
    background: #10b981;
    border-color: #10b981;
    color: white;
}

/* زر التأكيد */
.confirm-order-btn {
    width: 100%;
    padding: 16px 20px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6, #a855f7);
    background-size: 200% 200%;
    color: white;
    border: none;
    border-radius: 14px;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
    animation: gradient 3s ease infinite;
    margin-bottom: 15px;
}

@keyframes gradient {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

.confirm-order-btn:hover { transform: translateY(-2px); }

.btn-total {
    padding: 6px 12px;
    background: rgba(255,255,255,0.2);
    border-radius: 20px;
    font-size: 14px;
}

/* الضمانات */
.guarantees-bar {
    display: flex;
    justify-content: center;
    gap: 20px;
    flex-wrap: wrap;
    font-size: 11px;
    color: #888;
}

.guarantees-bar span { display: flex; align-items: center; gap: 5px; }
.guarantees-bar i { color: #10b981; }

/* Modal */
.modal {
    display: none;
    position: fixed;
    z-index: 9999;
    inset: 0;
    background: rgba(0,0,0,0.85);
    backdrop-filter: blur(10px);
    align-items: center;
    justify-content: center;
}

.modal-box {
    background: linear-gradient(145deg, #1e1e2e, #252538);
    padding: 35px 25px;
    border-radius: 24px;
    text-align: center;
    max-width: 350px;
    width: 90%;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.success-icon {
    width: 70px;
    height: 70px;
    background: rgba(16, 185, 129, 0.15);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 30px;
    color: #10b981;
}

.modal-box h2 { font-size: 20px; color: #fff; margin: 0 0 10px; }
.modal-box p { color: #888; font-size: 14px; margin: 0 0 20px; }
.modal-box p strong { color: #10b981; font-size: 18px; }

.modal-btns { display: flex; gap: 10px; }

.btn-primary, .btn-secondary {
    flex: 1;
    padding: 12px;
    border: none;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.btn-primary { background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; }
.btn-secondary { background: rgba(99, 102, 241, 0.1); color: #8b5cf6; }

/* Responsive */
@media (max-width: 400px) {
    .checkout-simple { padding: 10px; }
    .invoice-header-bar { flex-direction: column; gap: 10px; }
    .invoice-total-badge { width: 100%; justify-content: center; }
    .method-option { padding: 10px; }
    .method-icon { width: 35px; height: 35px; font-size: 14px; }
}
</style>

<script>
let map, marker;
let subtotal = 0, shippingCost = 0, paymentFee = 0;
const currency = "<?php echo getCurrencySymbol(); ?>";
const taxRate = <?php echo getTaxRate(); ?>;
const userId = "<?php echo $_SESSION['user_id']; ?>";
const cartKey = 'cart_' + userId;

document.addEventListener('DOMContentLoaded', function() {
    // تهيئة الخريطة
    map = L.map('map').setView([15.3694, 44.1910], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
    marker = L.marker([15.3694, 44.1910], {draggable: true}).addTo(map);
    
    marker.on('dragend', function(e) {
        let pos = marker.getLatLng();
        setLocation(pos.lat, pos.lng);
    });
    
    loadItems();
    updateTotals();
});

function setLocation(lat, lng) {
    document.getElementById('lat').value = lat;
    document.getElementById('lng').value = lng;
    document.getElementById('address').value = `موقع: ${lat.toFixed(4)}, ${lng.toFixed(4)}`;
    document.getElementById('location-badge').textContent = 'تم ✓';
    document.getElementById('location-badge').className = 'status-badge done';
}

function getMyLocation() {
    if (!navigator.geolocation) { alert("المتصفح لا يدعم GPS"); return; }
    
    const btn = document.querySelector('.locate-btn');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري...';
    btn.disabled = true;
    
    navigator.geolocation.getCurrentPosition((pos) => {
        map.setView([pos.coords.latitude, pos.coords.longitude], 17);
        marker.setLatLng([pos.coords.latitude, pos.coords.longitude]);
        setLocation(pos.coords.latitude, pos.coords.longitude);
        btn.innerHTML = '<i class="fas fa-check"></i> تم التحديد';
        btn.classList.add('success');
        btn.disabled = false;
    }, () => {
        alert("فشل تحديد الموقع");
        btn.innerHTML = '<i class="fas fa-location-arrow"></i> تحديد موقعي';
        btn.disabled = false;
    });
}

function loadItems() {
    let cart = JSON.parse(localStorage.getItem(cartKey)) || [];
    const container = document.getElementById('checkout-items');
    
    if (cart.length === 0) {
        container.innerHTML = '<p style="text-align:center;color:#888;padding:20px;">السلة فارغة</p>';
        return;
    }
    
    container.innerHTML = '';
    subtotal = 0;
    
    cart.forEach(item => {
        const total = item.price * item.quantity;
        subtotal += total;
        const imgSrc = item.image && item.image.trim() ? item.image : '';
        container.innerHTML += `
            <div class="invoice-item">
                ${imgSrc ? `<img src="${imgSrc}" alt="${item.name}" style="background:#2a2a3e;">` : '<div class="item-icon"><i class="fas fa-box"></i></div>'}
                <div class="invoice-item-info">
                    <h4>${item.name}</h4>
                    <span>×${item.quantity}</span>
                </div>
                <div class="invoice-item-price">${total.toFixed(2)} ${currency}</div>
            </div>
        `;
    });
    
    document.getElementById('checkout-subtotal').textContent = subtotal.toFixed(2) + ' ' + currency;
}

function updateTotals() {
    // الشحن
    const ship = document.querySelector('input[name="shipping"]:checked');
    if (ship) {
        const cost = parseFloat(ship.dataset.cost) || 0;
        const freeMin = parseFloat(ship.dataset.freeMin) || 0;
        const type = ship.dataset.type;
        
        if (type === 'free' || (freeMin > 0 && subtotal >= freeMin)) {
            shippingCost = 0;
            document.getElementById('checkout-shipping').innerHTML = '<span style="color:#10b981">مجاني</span>';
        } else {
            shippingCost = cost;
            document.getElementById('checkout-shipping').textContent = cost.toFixed(2) + ' ' + currency;
        }
    }
    
    // رسوم الدفع
    const pay = document.querySelector('input[name="payment"]:checked');
    if (pay) {
        const feeType = pay.dataset.feeType;
        const feeAmount = parseFloat(pay.dataset.feeAmount) || 0;
        
        if (feeType === 'none' || feeAmount === 0) {
            paymentFee = 0;
            document.getElementById('fee-row').style.display = 'none';
        } else {
            paymentFee = feeType === 'percentage' ? (subtotal * feeAmount / 100) : feeAmount;
            document.getElementById('checkout-fee').textContent = paymentFee.toFixed(2) + ' ' + currency;
            document.getElementById('fee-row').style.display = 'flex';
        }
    }
    
    // الإجمالي
    const beforeTax = subtotal + shippingCost + paymentFee;
    const tax = beforeTax * taxRate;
    const total = beforeTax + tax;
    
    document.getElementById('checkout-tax').textContent = tax.toFixed(2) + ' ' + currency;
    document.getElementById('checkout-total').textContent = total.toFixed(2) + ' ' + currency;
    document.getElementById('btn-total').textContent = total.toFixed(2) + ' ' + currency;
    
    // تحديث التحديد المرئي
    document.querySelectorAll('.method-option').forEach(m => {
        m.classList.toggle('selected', m.querySelector('input').checked);
    });
}

function submitOrder() {
    let cart = JSON.parse(localStorage.getItem(cartKey)) || [];
    
    if (cart.length === 0) { alert("السلة فارغة!"); return; }
    if (!document.getElementById('lat').value) { alert("حدد موقعك أولاً!"); return; }
    
    const btn = document.querySelector('.confirm-order-btn');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الإرسال...';
    btn.disabled = true;
    
    const beforeTax = subtotal + shippingCost + paymentFee;
    const tax = beforeTax * taxRate;
    const total = beforeTax + tax;
    
    fetch('save_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            name: document.getElementById('full_name').value,
            phone: document.getElementById('phone').value,
            address: document.getElementById('address').value,
            lat: document.getElementById('lat').value,
            lng: document.getElementById('lng').value,
            subtotal: subtotal,
            shipping_cost: shippingCost,
            payment_fee: paymentFee,
            tax: tax,
            total: total,
            shipping_method: document.querySelector('input[name="shipping"]:checked')?.value || 'standard',
            payment_method: document.querySelector('input[name="payment"]:checked')?.value || 'cod',
            items: cart
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            localStorage.removeItem(cartKey);
            if (typeof updateCartCounter === 'function') updateCartCounter();
            document.getElementById('order-id').textContent = '#' + data.order_id;
            document.getElementById('success-modal').style.display = 'flex';
        } else {
            alert("خطأ: " + data.message);
            btn.innerHTML = '<i class="fas fa-lock"></i><span>تأكيد الطلب</span><span class="btn-total">' + total.toFixed(2) + ' ' + currency + '</span>';
            btn.disabled = false;
        }
    })
    .catch(() => {
        alert("فشل الاتصال!");
        btn.innerHTML = '<i class="fas fa-lock"></i><span>تأكيد الطلب</span><span class="btn-total">' + total.toFixed(2) + ' ' + currency + '</span>';
        btn.disabled = false;
    });
}
</script>

<?php include 'footer.php'; ?>

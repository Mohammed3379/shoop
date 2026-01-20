<?php
/**
 * ===========================================
 * صفحة سلة المشتريات
 * ===========================================
 * 
 * تعرض المنتجات المضافة للسلة مع إمكانية:
 * - تعديل الكميات
 * - حذف المنتجات
 * - عرض ملخص الأسعار
 * - الانتقال للدفع
 * 
 * @package MyShop
 * @version 2.0
 */

include 'header.php';
?>



<!-- ========================================
     تصميم عصري لصفحة السلة
     ======================================== -->
<style>
/* ========== الحاوية الرئيسية ========== */
.cart-page-new {
    max-width: 1200px;
    margin: 0 auto;
    padding: 30px 20px;
    min-height: 60vh;
}

/* ========== العنوان ========== */
.cart-header-new {
    text-align: center;
    margin-bottom: 40px;
    animation: fadeInDown 0.6s ease;
}

@keyframes fadeInDown {
    from { opacity: 0; transform: translateY(-30px); }
    to { opacity: 1; transform: translateY(0); }
}

.cart-header-new h1 {
    font-size: 36px;
    font-weight: 800;
    margin: 0 0 10px 0;
    display: inline-flex;
    align-items: center;
    gap: 15px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6, #a855f7);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.cart-header-new h1 i {
    font-size: 40px;
}

.cart-header-new .items-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(139, 92, 246, 0.1));
    padding: 8px 20px;
    border-radius: 25px;
    font-size: 14px;
    color: #8b5cf6;
    font-weight: 600;
    margin-top: 10px;
}

/* ========== تخطيط السلة ========== */
.cart-layout-new {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 30px;
    animation: fadeInUp 0.6s ease 0.2s both;
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

@media (max-width: 992px) {
    .cart-layout-new {
        grid-template-columns: 1fr;
    }
}

/* ========== قائمة المنتجات ========== */
.cart-items-new {
    background: linear-gradient(145deg, #1e1e2e, #252538);
    border-radius: 24px;
    overflow: hidden;
    border: 1px solid rgba(99, 102, 241, 0.1);
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
}

.cart-items-header-new {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 60px;
    padding: 18px 25px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.05));
    font-size: 13px;
    font-weight: 600;
    color: #888;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

@media (max-width: 768px) {
    .cart-items-header-new { display: none; }
}

/* ========== عنصر المنتج ========== */
.cart-item-new {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 60px;
    align-items: center;
    padding: 20px 25px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    transition: all 0.3s ease;
}

.cart-item-new:hover {
    background: rgba(99, 102, 241, 0.05);
}

.cart-item-new:last-child {
    border-bottom: none;
}

@media (max-width: 768px) {
    .cart-item-new {
        grid-template-columns: 1fr;
        gap: 15px;
        padding: 20px;
    }
}

/* ========== معلومات المنتج ========== */
.item-info-new {
    display: flex;
    align-items: center;
    gap: 18px;
}

.item-image-new {
    width: 90px;
    height: 90px;
    border-radius: 16px;
    overflow: hidden;
    background: linear-gradient(145deg, #2a2a3e, #1a1a28);
    flex-shrink: 0;
    border: 2px solid rgba(99, 102, 241, 0.1);
    transition: all 0.3s ease;
}

.item-image-new:hover {
    border-color: rgba(99, 102, 241, 0.3);
    transform: scale(1.05);
}

.item-image-new img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.item-details-new h3 {
    margin: 0 0 8px 0;
    font-size: 16px;
    font-weight: 600;
    color: #fff;
    line-height: 1.4;
}

.item-price-single {
    font-size: 14px;
    color: #8b5cf6;
    font-weight: 600;
}

/* ========== التحكم بالكمية ========== */
.quantity-control-new {
    display: flex;
    align-items: center;
    gap: 5px;
    background: rgba(99, 102, 241, 0.08);
    border-radius: 14px;
    padding: 6px;
    width: fit-content;
}

.qty-btn-new {
    width: 38px;
    height: 38px;
    border: none;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border-radius: 10px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.qty-btn-new:hover {
    transform: scale(1.1);
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
}

.qty-btn-new:active {
    transform: scale(0.95);
}

.qty-value-new {
    min-width: 45px;
    text-align: center;
    font-size: 18px;
    font-weight: 700;
    color: #fff;
}

/* ========== إجمالي المنتج ========== */
.item-total-new {
    font-size: 18px;
    font-weight: 700;
    background: linear-gradient(135deg, #10b981, #34d399);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* ========== زر الحذف ========== */
.delete-btn-new {
    width: 45px;
    height: 45px;
    border: none;
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border-radius: 12px;
    cursor: pointer;
    font-size: 16px;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.delete-btn-new:hover {
    background: #ef4444;
    color: white;
    transform: scale(1.1);
    box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4);
}

/* ========== ملخص الطلب ========== */
.order-summary-new {
    background: linear-gradient(145deg, #1e1e2e, #252538);
    border-radius: 24px;
    padding: 30px;
    border: 1px solid rgba(99, 102, 241, 0.15);
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    position: sticky;
    top: 100px;
    height: fit-content;
}

.summary-title-new {
    font-size: 20px;
    font-weight: 700;
    margin: 0 0 25px 0;
    display: flex;
    align-items: center;
    gap: 12px;
    padding-bottom: 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.summary-title-new i {
    color: #8b5cf6;
    font-size: 22px;
}

.summary-row-new {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 0;
    font-size: 15px;
    color: #aaa;
}

.summary-row-new.total-row {
    margin-top: 15px;
    padding-top: 20px;
    border-top: 2px dashed rgba(99, 102, 241, 0.2);
    font-size: 18px;
    font-weight: 700;
    color: #fff;
}

.summary-row-new.total-row span:last-child {
    font-size: 26px;
    background: linear-gradient(135deg, #10b981, #34d399);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* ========== كود الخصم ========== */
.promo-code-new {
    margin: 25px 0;
    display: flex;
    gap: 10px;
}

.promo-input-new {
    flex: 1;
    padding: 14px 18px;
    background: rgba(99, 102, 241, 0.08);
    border: 2px solid rgba(99, 102, 241, 0.15);
    border-radius: 12px;
    color: #fff;
    font-size: 14px;
    transition: all 0.3s ease;
}

.promo-input-new:focus {
    outline: none;
    border-color: #8b5cf6;
    box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.15);
}

.promo-input-new::placeholder {
    color: #666;
}

.promo-btn-new {
    padding: 14px 20px;
    background: rgba(99, 102, 241, 0.15);
    border: none;
    border-radius: 12px;
    color: #8b5cf6;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.promo-btn-new:hover {
    background: rgba(99, 102, 241, 0.25);
}

/* ========== أزرار الإجراءات ========== */
.checkout-btn-new {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    width: 100%;
    padding: 18px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6, #a855f7);
    background-size: 200% 200%;
    color: white;
    text-decoration: none;
    border-radius: 16px;
    font-size: 17px;
    font-weight: 700;
    box-shadow: 0 8px 30px rgba(99, 102, 241, 0.4);
    transition: all 0.4s ease;
    animation: gradientShift 3s ease infinite;
    margin-bottom: 15px;
}

@keyframes gradientShift {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

.checkout-btn-new:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 40px rgba(99, 102, 241, 0.5);
    color: white;
}

.checkout-btn-new i {
    font-size: 18px;
}

.continue-btn-new {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    width: 100%;
    padding: 14px;
    background: transparent;
    border: 2px solid rgba(99, 102, 241, 0.2);
    color: #8b5cf6;
    text-decoration: none;
    border-radius: 14px;
    font-size: 15px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.continue-btn-new:hover {
    background: rgba(99, 102, 241, 0.1);
    border-color: rgba(99, 102, 241, 0.4);
    color: #8b5cf6;
}

/* ========== ميزات الأمان ========== */
.security-features {
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
}

.security-item {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    color: #888;
    margin-bottom: 10px;
}

.security-item i {
    color: #10b981;
    font-size: 14px;
}

/* ========== السلة الفارغة ========== */
.empty-cart-new {
    text-align: center;
    padding: 80px 20px;
    animation: fadeInUp 0.6s ease;
}

.empty-cart-icon-new {
    width: 150px;
    height: 150px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(139, 92, 246, 0.08));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 30px;
    position: relative;
}

.empty-cart-icon-new::before {
    content: '';
    position: absolute;
    width: 180px;
    height: 180px;
    border: 2px dashed rgba(99, 102, 241, 0.2);
    border-radius: 50%;
    animation: spin 20s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.empty-cart-icon-new i {
    font-size: 60px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.empty-cart-new h2 {
    font-size: 28px;
    font-weight: 700;
    color: #fff;
    margin: 0 0 15px 0;
}

.empty-cart-new p {
    color: #888;
    font-size: 16px;
    margin: 0 0 30px 0;
    max-width: 400px;
    margin-left: auto;
    margin-right: auto;
    line-height: 1.7;
}

.shop-now-btn-new {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    padding: 16px 35px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    text-decoration: none;
    border-radius: 14px;
    font-size: 16px;
    font-weight: 700;
    box-shadow: 0 8px 30px rgba(99, 102, 241, 0.4);
    transition: all 0.3s ease;
}

.shop-now-btn-new:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 40px rgba(99, 102, 241, 0.5);
    color: white;
}

/* ========== عرض الموبايل ========== */
@media (max-width: 768px) {
    .cart-header-new h1 { font-size: 28px; }
    .item-info-new { flex-direction: column; text-align: center; }
    .item-image-new { width: 100px; height: 100px; }
    .quantity-control-new { margin: 0 auto; }
    .item-total-new { text-align: center; font-size: 20px; }
    .delete-btn-new { margin: 0 auto; }
    .order-summary-new { position: static; }
}
</style>

<section class="cart-page-new">
    
    <!-- عنوان الصفحة -->
    <div class="cart-header-new">
        <h1><i class="fas fa-shopping-cart"></i> سلة المشتريات</h1>
        <div class="items-badge">
            <i class="fas fa-box"></i>
            <span id="items-count">0 منتج</span>
        </div>
    </div>
    
    <!-- المحتوى الرئيسي -->
    <div id="cart-content">
        <!-- يتم تحميله بواسطة JavaScript -->
    </div>
    
</section>

<!-- ========================================
     سكريبت السلة
     ======================================== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // محاولة تحميل السلة من الخادم أولاً (للمستخدمين المسجلين)
    loadCartFromServer().then(() => {
        renderCart();
    });
});

/**
 * تحميل السلة من الخادم
 */
async function loadCartFromServer() {
    // فقط للمستخدمين المسجلين
    if (typeof activeUserId === 'undefined' || activeUserId === 'guest') {
        return;
    }
    
    try {
        const response = await fetch('api/sync_cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ action: 'get' })
        });
        
        const data = await response.json();
        
        if (data.success && data.cart && data.cart.length > 0) {
            const cartKey = 'cart_' + activeUserId;
            const localCart = JSON.parse(localStorage.getItem(cartKey)) || [];
            
            // دمج السلة المحلية مع السلة من الخادم
            if (localCart.length === 0) {
                // إذا كانت السلة المحلية فارغة، استخدم سلة الخادم
                localStorage.setItem(cartKey, JSON.stringify(data.cart));
            }
        }
    } catch (error) {
        console.error('Error loading cart from server:', error);
    }
}

/**
 * عرض محتوى السلة - تصميم عصري
 */
function renderCart() {
    const container = document.getElementById('cart-content');
    const itemsCount = document.getElementById('items-count');
    
    // جلب السلة من التخزين المحلي
    const userKey = (typeof activeUserId !== 'undefined') ? activeUserId : 'guest';
    const cartKey = 'cart_' + userKey;
    let cart = JSON.parse(localStorage.getItem(cartKey)) || [];
    
    // تحديث عدد المنتجات
    const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
    itemsCount.textContent = totalItems + ' منتج';
    
    // إذا كانت السلة فارغة
    if (cart.length === 0) {
        container.innerHTML = `
            <div class="empty-cart-new">
                <div class="empty-cart-icon-new">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h2>سلة المشتريات فارغة</h2>
                <p>لم تقم بإضافة أي منتجات بعد. تصفح متجرنا واكتشف أفضل العروض والمنتجات المميزة!</p>
                <a href="index.php" class="shop-now-btn-new">
                    <i class="fas fa-store"></i>
                    ابدأ التسوق الآن
                </a>
            </div>
        `;
        return;
    }
    
    // حساب المجاميع
    let subtotal = 0;
    cart.forEach(item => {
        subtotal += item.price * item.quantity;
    });
    const taxRate = (typeof CURRENCY !== 'undefined') ? CURRENCY.taxRate : 0.15;
    const taxPercent = Math.round(taxRate * 100);
    const tax = subtotal * taxRate;
    const total = subtotal + tax;
    
    // بناء HTML للمنتجات
    let itemsHTML = '';
    cart.forEach((item, index) => {
        const itemTotal = item.price * item.quantity;
        itemsHTML += `
            <div class="cart-item-new" data-index="${index}" style="animation: fadeInUp 0.4s ease ${index * 0.1}s both;">
                <div class="item-info-new">
                    <div class="item-image-new">
                        <img src="${escapeHTML(item.image)}" alt="${escapeHTML(item.name)}">
                    </div>
                    <div class="item-details-new">
                        <h3>${escapeHTML(item.name)}</h3>
                        <div class="item-price-single">${formatPrice(item.price)}</div>
                    </div>
                </div>
                
                <div class="quantity-control-new">
                    <button class="qty-btn-new" onclick="updateQty(${index}, -1)">
                        <i class="fas fa-minus"></i>
                    </button>
                    <span class="qty-value-new">${item.quantity}</span>
                    <button class="qty-btn-new" onclick="updateQty(${index}, 1)">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                
                <div class="item-total-new">${formatPrice(itemTotal)}</div>
                
                <button class="delete-btn-new" onclick="removeItem(${index})" title="حذف المنتج">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>
        `;
    });

    container.innerHTML = `
        <div class="cart-layout-new">
            <!-- قائمة المنتجات -->
            <div class="cart-items-new">
                <div class="cart-items-header-new">
                    <span>المنتج</span>
                    <span>الكمية</span>
                    <span>الإجمالي</span>
                    <span></span>
                </div>
                <div id="cart-items-list">
                    ${itemsHTML}
                </div>
            </div>
            
            <!-- ملخص الطلب -->
            <div class="order-summary-new">
                <h3 class="summary-title-new">
                    <i class="fas fa-receipt"></i>
                    ملخص الطلب
                </h3>
                
                <div class="summary-row-new">
                    <span>المجموع الفرعي (${cart.length} منتج)</span>
                    <span style="color:#fff; font-weight:600;">${formatPrice(subtotal)}</span>
                </div>
                
                <div class="summary-row-new">
                    <span>الضريبة (${taxPercent}%)</span>
                    <span>${formatPrice(tax)}</span>
                </div>
                
                <div class="summary-row-new">
                    <span>رسوم التوصيل</span>
                    <span style="color:#10b981; font-size:13px;">يُحسب عند الدفع</span>
                </div>
                
                <div class="summary-row-new total-row">
                    <span>الإجمالي</span>
                    <span>${formatPrice(total)}</span>
                </div>
                
                <!-- كود الخصم -->
                <div class="promo-code-new">
                    <input type="text" class="promo-input-new" placeholder="كود الخصم">
                    <button class="promo-btn-new">تطبيق</button>
                </div>
                
                <a href="checkout.php" class="checkout-btn-new">
                    <i class="fas fa-lock"></i>
                    إتمام الشراء الآمن
                </a>
                
                <a href="index.php" class="continue-btn-new">
                    <i class="fas fa-arrow-right"></i>
                    متابعة التسوق
                </a>
                
                <!-- ميزات الأمان -->
                <div class="security-features">
                    <div class="security-item">
                        <i class="fas fa-shield-alt"></i>
                        <span>دفع آمن 100%</span>
                    </div>
                    <div class="security-item">
                        <i class="fas fa-undo"></i>
                        <span>إرجاع مجاني خلال 14 يوم</span>
                    </div>
                    <div class="security-item">
                        <i class="fas fa-truck"></i>
                        <span>توصيل سريع لجميع المناطق</span>
                    </div>
                </div>
            </div>
        </div>
    `;
}

/**
 * تحديث الكمية
 */
function updateQty(index, change) {
    const userKey = (typeof activeUserId !== 'undefined') ? activeUserId : 'guest';
    const cartKey = 'cart_' + userKey;
    let cart = JSON.parse(localStorage.getItem(cartKey)) || [];
    
    if (cart[index]) {
        cart[index].quantity += change;
        
        if (cart[index].quantity < 1) {
            cart[index].quantity = 1;
        }
        
        localStorage.setItem(cartKey, JSON.stringify(cart));
        renderCart();
        updateCartCounter();
    }
}

/**
 * حذف منتج
 */
function removeItem(index) {
    const userKey = (typeof activeUserId !== 'undefined') ? activeUserId : 'guest';
    const cartKey = 'cart_' + userKey;
    let cart = JSON.parse(localStorage.getItem(cartKey)) || [];
    
    if (cart[index]) {
        const itemName = cart[index].name;
        cart.splice(index, 1);
        localStorage.setItem(cartKey, JSON.stringify(cart));
        renderCart();
        updateCartCounter();
        showToast('تم حذف ' + itemName + ' من السلة');
    }
}

/**
 * تنظيف النص من HTML
 */
function escapeHTML(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

/**
 * تحديث عداد السلة
 */
function updateCartCounter() {
    const userKey = (typeof activeUserId !== 'undefined') ? activeUserId : 'guest';
    const cartKey = 'cart_' + userKey;
    let cart = JSON.parse(localStorage.getItem(cartKey)) || [];
    
    const totalCount = cart.reduce((sum, item) => sum + item.quantity, 0);
    
    const counter = document.getElementById('cart-count');
    if (counter) counter.textContent = totalCount;
    
    const mobileCounter = document.getElementById('mobile-cart-count');
    if (mobileCounter) mobileCounter.textContent = totalCount;
    
    // مزامنة السلة مع قاعدة البيانات (للمستخدمين المسجلين فقط)
    syncCartToServer(cart);
}

/**
 * مزامنة السلة مع الخادم
 */
function syncCartToServer(cart) {
    // فقط للمستخدمين المسجلين
    if (typeof activeUserId === 'undefined' || activeUserId === 'guest') {
        return;
    }
    
    fetch('api/sync_cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: 'sync',
            cart: cart
        })
    })
    .then(response => response.json())
    .then(data => {
        // يمكن إضافة معالجة إضافية هنا
        console.log('Cart synced:', data);
    })
    .catch(error => {
        console.error('Cart sync error:', error);
    });
}

/**
 * عرض رسالة تنبيه
 */
function showToast(message) {
    const toast = document.getElementById('toast');
    if (!toast) return;
    
    toast.textContent = message + ' ✅';
    toast.className = 'show';
    
    setTimeout(() => {
        toast.className = '';
    }, 3000);
}

// تحديث عند تغيير حجم الشاشة
window.addEventListener('resize', function() {
    const actions = document.querySelectorAll('.cart-item-actions');
    actions.forEach(el => {
        el.style.display = window.innerWidth <= 768 ? 'flex' : 'none';
    });
});
</script>

<?php include 'footer.php'; ?>

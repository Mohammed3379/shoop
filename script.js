/* script.js - نظام السلة الذكي (لكل مستخدم سلة خاصة) */

// 1. تحديد مفاتيح التخزين بناءً على المتغير القادم من PHP
// إذا لم يكن activeUserId موجوداً (زائر)، نعتبره 'guest'
var userKey = (typeof activeUserId !== 'undefined') ? activeUserId : 'guest';

var CART_KEY = 'cart_' + userKey;         // مثال: cart_5 أو cart_guest
var WISHLIST_KEY = 'wishlist_' + userKey; // مثال: wishlist_5

// طباعة للتأكد في الكونسول (للمطور)
console.log("نظام السلة يعمل للمستخدم رقم:", userKey);

// 2. تحميل البيانات الخاصة بهذا المستخدم فقط
var cart = JSON.parse(localStorage.getItem(CART_KEY)) || [];
var wishlistData = JSON.parse(localStorage.getItem(WISHLIST_KEY)) || [];

// 3. تحديث العدادات فور تحميل الصفحة
document.addEventListener("DOMContentLoaded", function() {
    updateCartCounter();
    updateWishlistCounter();

    // تشغيل دوال العرض حسب الصفحة الحالية
    if (document.getElementById('cart-table')) displayCartItems();
    if (document.getElementById('wishlist-container')) displayWishlist();
    if (window.location.pathname.includes('checkout.php')) loadCheckoutSummary();
});


// ===========================
//    إدارة السلة (Cart)
// ===========================

function addToCart(name, price, image) {
    // البحث هل المنتج موجود مسبقاً؟
    let existingItem = cart.find(item => item.name === name);

    if (existingItem) {
        existingItem.quantity += 1;
        showToast(`تم زيادة كمية: ${name}`);
    } else {
        cart.push({ name: name, price: price, image: image, quantity: 1 });
        showToast(`تمت إضافة ${name} للسلة!`);
    }
    saveCart(); // حفظ التغييرات
}

function changeQuantity(index, change) {
    if (cart[index].quantity === 1 && change === -1) return; // لا تقل عن 1
    cart[index].quantity += change;
    saveCart();
}

function removeFromCart(index) {
    cart.splice(index, 1); // حذف العنصر
    saveCart();
}

// دالة الحفظ (تستخدم المفتاح الخاص بالمستخدم)
function saveCart() {
    localStorage.setItem(CART_KEY, JSON.stringify(cart)); 
    updateCartCounter();
    // إذا كنا في صفحة السلة، نحدث الجدول فوراً
    if (document.getElementById('cart-table')) displayCartItems();
}

function updateCartCounter() {
    const counter = document.getElementById('cart-count');
    if(counter) {
        // حساب مجموع الكميات (وليس عدد الأصناف فقط)
        const totalCount = cart.reduce((sum, item) => sum + item.quantity, 0);
        counter.innerText = totalCount;
    }
}

function displayCartItems() {
    const tbody = document.getElementById('cart-body');
    const emptyMsg = document.getElementById('empty-cart-msg');
    const table = document.getElementById('cart-table');
    
    if(!tbody) return;

    tbody.innerHTML = '';

    if (cart.length === 0) {
        if(table) table.style.display = 'none';
        if(emptyMsg) emptyMsg.style.display = 'block';
        updateSummary(0); // تصفير المجموع
        return;
    }

    if(table) table.style.display = 'table';
    if(emptyMsg) emptyMsg.style.display = 'none';

    let total = 0;
    cart.forEach((item, index) => {
        let itemTotal = item.price * item.quantity;
        total += itemTotal;
        tbody.innerHTML += `
            <tr>
                <td>
                    <div class="product-info">
                        <img src="${item.image}" alt="${item.name}">
                        <div>
                            <span style="font-weight:bold; display:block; font-size:14px;">${item.name}</span>
                            <span style="font-size:12px; color:#888;">${formatPrice(item.price)}</span>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="quantity-controls">
                        <button onclick="changeQuantity(${index}, -1)">-</button>
                        <span>${item.quantity}</span>
                        <button onclick="changeQuantity(${index}, 1)">+</button>
                    </div>
                </td>
                <td style="color:#28a745; font-weight:bold; font-size:14px;">${formatPrice(itemTotal)}</td>
                <td><button class="remove-btn" onclick="removeFromCart(${index})"><i class="fas fa-trash-alt"></i></button></td>
            </tr>
        `;
    });
    updateSummary(total);
}

function updateSummary(subtotal) {
    // استخدام نسبة الضريبة من إعدادات العملة
    const taxRate = (typeof CURRENCY !== 'undefined') ? CURRENCY.taxRate : 0.15;
    const tax = subtotal * taxRate;
    const total = subtotal + tax;
    
    if(document.getElementById('subtotal')) document.getElementById('subtotal').innerText = formatPrice(subtotal);
    if(document.getElementById('tax')) document.getElementById('tax').innerText = formatPrice(tax);
    if(document.getElementById('total')) document.getElementById('total').innerText = formatPrice(total);
}


// ===========================
//    إدارة المفضلة (Wishlist)
// ===========================

function toggleWishlist(id, name, image, price) {
    const index = wishlistData.findIndex(item => item.id === id);
    const btn = event ? event.currentTarget : null; // الزر الذي تم ضغطه

    if (index === -1) {
        // إضافة
        wishlistData.push({ id, name, image, price });
        showToast('تمت الإضافة للمفضلة ❤️');
        if(btn) btn.classList.add('liked');
    } else {
        // حذف
        wishlistData.splice(index, 1);
        showToast('تم الحذف من المفضلة 💔');
        if(btn) btn.classList.remove('liked');
    }

    // الحفظ في صندوق المستخدم
    localStorage.setItem(WISHLIST_KEY, JSON.stringify(wishlistData));
    updateWishlistCounter();
    
    // تحديث العرض لو كنا في صفحة المفضلة
    if(document.getElementById('wishlist-container')) displayWishlist();
}

function updateWishlistCounter() {
    const counter = document.getElementById('wishlist-count');
    if(counter) counter.innerText = wishlistData.length;
}

function displayWishlist() {
    const container = document.getElementById('wishlist-container');
    if(!container) return;

    container.innerHTML = '';
    
    if (wishlistData.length === 0) {
        container.innerHTML = '<div style="text-align:center; width:100%; padding:40px;"><i class="far fa-heart" style="font-size:40px; color:#333; margin-bottom:10px;"></i><p style="color:#888;">قائمة المفضلة فارغة</p></div>';
        return;
    }

    wishlistData.forEach(product => {
        container.innerHTML += `
            <div class="card">
                <div class="img-container" style="position:relative;">
                    <button class="wishlist-icon liked" onclick="toggleWishlist(${product.id}, '${product.name}', '', 0)">
                        <i class="fas fa-times"></i>
                    </button>
                    <a href="product.php?id=${product.id}">
                        <img src="${product.image}" alt="${product.name}">
                    </a>
                </div>
                <div class="card-details">
                    <h3>${product.name}</h3>
                    <span class="price">${formatPrice(product.price)}</span>
                    <button class="add-btn" onclick="addToCart('${product.name}', ${product.price}, '${product.image}')">
                        نقل للسلة <i class="fas fa-cart-plus"></i>
                    </button>
                </div>
            </div>
        `;
    });
}


// ===========================
//    أدوات مساعدة (Toast & Checkout)
// ===========================

function showToast(message) {
    const toast = document.getElementById('toast');
    if(!toast) return;
    
    toast.innerText = message + " ✅";
    toast.className = "show";
    setTimeout(() => { toast.className = toast.className.replace("show", ""); }, 3000);
}

// عرض ملخص الطلب في صفحة الدفع
function loadCheckoutSummary() {
    // نستخدم المتغير cart الذي تم تحميله للمستخدم الصحيح
    const summaryContainer = document.getElementById('checkout-items');
    let subtotal = 0;
    
    if (cart.length === 0) {
        if(summaryContainer) summaryContainer.innerHTML = "<p>السلة فارغة!</p>";
        return;
    }

    if(summaryContainer) summaryContainer.innerHTML = ''; // تفريغ

    cart.forEach(item => {
        let itemTotal = item.price * item.quantity;
        subtotal += itemTotal;
        if(summaryContainer) {
            summaryContainer.innerHTML += `
                <div style="display:flex; justify-content:space-between; margin-bottom:10px; border-bottom:1px solid #333; padding-bottom:5px;">
                    <div>
                        <span style="color:white">${item.name}</span>
                        <span style="color:#888; font-size:12px"> (×${item.quantity})</span>
                    </div>
                    <span>${formatPrice(itemTotal)}</span>
                </div>
            `;
        }
    });

    // استخدام نسبة الضريبة من إعدادات العملة
    const taxRate = (typeof CURRENCY !== 'undefined') ? CURRENCY.taxRate : 0.15;
    const tax = subtotal * taxRate;
    const total = subtotal + tax;

    if(document.getElementById('checkout-subtotal')) document.getElementById('checkout-subtotal').innerText = formatPrice(subtotal);
    if(document.getElementById('checkout-tax')) document.getElementById('checkout-tax').innerText = formatPrice(tax);
    if(document.getElementById('checkout-total')) document.getElementById('checkout-total').innerText = formatPrice(total);
}

/* --- منطق سلايدر الإعلانات --- */
var slideIndex = 1;
var slideTimer;

// تشغيل السلايدر عند التحميل (فقط إذا كان موجوداً في الصفحة)
if(document.querySelector('.ad-slider')) {
    showSlides(slideIndex);
    // تحريك تلقائي كل 5 ثواني
    slideTimer = setInterval(() => { moveSlide(1); }, 5000);
}

function moveSlide(n) {
    showSlides(slideIndex += n);
    resetTimer(); // إعادة ضبط المؤقت عند التدخل اليدوي
}

function currentSlide(n) {
    showSlides(slideIndex = n);
    resetTimer();
}

function showSlides(n) {
    let i;
    let slides = document.getElementsByClassName("ad-slide");
    let dots = document.getElementsByClassName("dot");
    
    if (slides.length === 0) return;

    if (n > slides.length) {slideIndex = 1}    
    if (n < 1) {slideIndex = slides.length}
    
    // إخفاء الكل
    for (i = 0; i < slides.length; i++) {
        slides[i].style.display = "none";  
        slides[i].classList.remove('active');
    }
    // إزالة تفعيل النقاط
    for (i = 0; i < dots.length; i++) {
        dots[i].className = dots[i].className.replace(" active", "");
    }
    
    // إظهار الشريحة الحالية
    slides[slideIndex-1].style.display = "flex";  
    slides[slideIndex-1].classList.add('active');
    if(dots.length > 0) dots[slideIndex-1].className += " active";
}

function resetTimer() {
    clearInterval(slideTimer);
    slideTimer = setInterval(() => { moveSlide(1); }, 5000);
}
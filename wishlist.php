<?php
/**
 * صفحة المفضلة
 * تعرض المنتجات المحفوظة في المفضلة
 */
include 'header.php';
?>

<section class="wishlist-page">
    <div class="wishlist-container">
        
        <!-- عنوان الصفحة -->
        <div class="wishlist-header">
            <i class="fas fa-heart"></i>
            <h1>قائمة المفضلة</h1>
            <span id="wishlist-items-count">0 منتج</span>
        </div>
        
        <!-- محتوى المفضلة -->
        <div id="wishlist-content">
            <!-- يتم تحميله بواسطة JavaScript -->
        </div>
        
    </div>
</section>

<style>
.wishlist-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 30px 20px;
    min-height: 60vh;
}

.wishlist-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #2a2a2a;
}

.wishlist-header i {
    font-size: 35px;
    color: #ff3e3e;
}

.wishlist-header h1 {
    font-size: 28px;
    color: white;
    margin: 0;
}

.wishlist-header span {
    background: #ff3e3e;
    color: white;
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: bold;
}

.wishlist-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 25px;
}

.wishlist-card {
    background: #1a1a1a;
    border-radius: 15px;
    border: 1px solid #2a2a2a;
    overflow: hidden;
    transition: all 0.3s ease;
}

.wishlist-card:hover {
    transform: translateY(-5px);
    border-color: #ff3e3e;
}

.wishlist-card-image {
    position: relative;
    height: 200px;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
}

.wishlist-card-image img {
    max-width: 90%;
    max-height: 90%;
    object-fit: contain;
}

.remove-wishlist-btn {
    position: absolute;
    top: 10px;
    left: 10px;
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background: rgba(220, 53, 69, 0.9);
    color: white;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: 0.3s;
}

.remove-wishlist-btn:hover {
    background: #dc3545;
    transform: scale(1.1);
}

.wishlist-card-info {
    padding: 15px;
}

.wishlist-card-info h3 {
    color: white;
    font-size: 16px;
    margin: 0 0 10px;
    line-height: 1.4;
}

.wishlist-card-info .price {
    color: #28a745;
    font-size: 20px;
    font-weight: bold;
    display: block;
    margin-bottom: 15px;
}

.wishlist-card-actions {
    display: flex;
    gap: 10px;
}

.add-to-cart-from-wishlist {
    flex: 1;
    padding: 12px;
    background: linear-gradient(135deg, #ff3e3e, #ff6b6b);
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: bold;
    cursor: pointer;
    transition: 0.3s;
}

.add-to-cart-from-wishlist:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(255, 62, 62, 0.3);
}

.view-product-btn {
    padding: 12px 15px;
    background: #333;
    color: white;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    transition: 0.3s;
    text-decoration: none;
}

.view-product-btn:hover {
    background: #444;
}

.empty-wishlist {
    text-align: center;
    padding: 80px 20px;
    background: #1a1a1a;
    border-radius: 20px;
    border: 1px solid #2a2a2a;
}

.empty-wishlist-icon {
    font-size: 100px;
    color: #333;
    margin-bottom: 25px;
}

.empty-wishlist h2 {
    color: white;
    font-size: 26px;
    margin: 0 0 15px;
}

.empty-wishlist p {
    color: #888;
    font-size: 16px;
    margin: 0 0 30px;
}

.browse-products-btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 15px 40px;
    background: linear-gradient(135deg, #ff3e3e, #ff6b6b);
    color: white;
    text-decoration: none;
    border-radius: 50px;
    font-weight: bold;
    font-size: 16px;
    transition: all 0.3s ease;
}

.browse-products-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(255, 62, 62, 0.4);
}

@media (max-width: 768px) {
    .wishlist-header {
        flex-wrap: wrap;
    }
    .wishlist-header h1 {
        font-size: 22px;
    }
    .wishlist-grid {
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 15px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    renderWishlist();
});

function renderWishlist() {
    const container = document.getElementById('wishlist-content');
    const countSpan = document.getElementById('wishlist-items-count');
    
    const userKey = (typeof activeUserId !== 'undefined') ? activeUserId : 'guest';
    const wishlistKey = 'wishlist_' + userKey;
    let wishlist = JSON.parse(localStorage.getItem(wishlistKey)) || [];
    
    // تحديث العدد
    countSpan.textContent = wishlist.length + ' منتج';
    
    // إذا كانت المفضلة فارغة
    if (wishlist.length === 0) {
        container.innerHTML = `
            <div class="empty-wishlist">
                <div class="empty-wishlist-icon">
                    <i class="far fa-heart"></i>
                </div>
                <h2>قائمة المفضلة فارغة</h2>
                <p>لم تقم بإضافة أي منتجات للمفضلة بعد. تصفح المتجر وأضف ما يعجبك!</p>
                <a href="index.php" class="browse-products-btn">
                    <i class="fas fa-store"></i>
                    تصفح المنتجات
                </a>
            </div>
        `;
        return;
    }
    
    // بناء الشبكة
    let html = '<div class="wishlist-grid">';
    
    wishlist.forEach((item, index) => {
        html += `
            <div class="wishlist-card" data-index="${index}">
                <div class="wishlist-card-image">
                    <button class="remove-wishlist-btn" onclick="removeFromWishlist(${index})" title="إزالة من المفضلة">
                        <i class="fas fa-times"></i>
                    </button>
                    <a href="product.php?id=${item.id}">
                        <img src="${escapeHTML(item.image)}" alt="${escapeHTML(item.name)}">
                    </a>
                </div>
                <div class="wishlist-card-info">
                    <h3>${escapeHTML(item.name)}</h3>
                    <span class="price">${formatPrice(item.price)}</span>
                    <div class="wishlist-card-actions">
                        <button class="add-to-cart-from-wishlist" onclick="moveToCart(${index})">
                            <i class="fas fa-cart-plus"></i> أضف للسلة
                        </button>
                        <a href="product.php?id=${item.id}" class="view-product-btn">
                            <i class="fas fa-eye"></i>
                        </a>
                    </div>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
}

function removeFromWishlist(index) {
    const userKey = (typeof activeUserId !== 'undefined') ? activeUserId : 'guest';
    const wishlistKey = 'wishlist_' + userKey;
    let wishlist = JSON.parse(localStorage.getItem(wishlistKey)) || [];
    
    if (wishlist[index]) {
        const itemName = wishlist[index].name;
        wishlist.splice(index, 1);
        localStorage.setItem(wishlistKey, JSON.stringify(wishlist));
        renderWishlist();
        updateWishlistCounter();
        showToast('تم إزالة ' + itemName + ' من المفضلة');
    }
}

function moveToCart(index) {
    const userKey = (typeof activeUserId !== 'undefined') ? activeUserId : 'guest';
    const wishlistKey = 'wishlist_' + userKey;
    const cartKey = 'cart_' + userKey;
    
    let wishlist = JSON.parse(localStorage.getItem(wishlistKey)) || [];
    let cart = JSON.parse(localStorage.getItem(cartKey)) || [];
    
    if (wishlist[index]) {
        const item = wishlist[index];
        
        // إضافة للسلة
        const existingItem = cart.find(i => i.name === item.name);
        if (existingItem) {
            existingItem.quantity += 1;
        } else {
            cart.push({ name: item.name, price: item.price, image: item.image, quantity: 1 });
        }
        
        localStorage.setItem(cartKey, JSON.stringify(cart));
        
        // إزالة من المفضلة
        wishlist.splice(index, 1);
        localStorage.setItem(wishlistKey, JSON.stringify(wishlist));
        
        renderWishlist();
        updateCartCounter();
        updateWishlistCounter();
        showToast('تم نقل المنتج للسلة ✅');
    }
}

function escapeHTML(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function updateCartCounter() {
    const userKey = (typeof activeUserId !== 'undefined') ? activeUserId : 'guest';
    const cart = JSON.parse(localStorage.getItem('cart_' + userKey)) || [];
    const count = cart.reduce((sum, item) => sum + item.quantity, 0);
    
    const counter = document.getElementById('cart-count');
    if (counter) counter.textContent = count;
    
    const mobileCounter = document.getElementById('mobile-cart-count');
    if (mobileCounter) mobileCounter.textContent = count;
}

function updateWishlistCounter() {
    const userKey = (typeof activeUserId !== 'undefined') ? activeUserId : 'guest';
    const wishlist = JSON.parse(localStorage.getItem('wishlist_' + userKey)) || [];
    
    const counter = document.getElementById('wishlist-count');
    if (counter) counter.textContent = wishlist.length;
    
    const mobileCounter = document.getElementById('mobile-wishlist-count');
    if (mobileCounter) mobileCounter.textContent = wishlist.length;
}

function showToast(message) {
    const toast = document.getElementById('toast');
    if (!toast) return;
    
    toast.textContent = message;
    toast.className = 'show';
    
    setTimeout(() => {
        toast.className = '';
    }, 3000);
}
</script>

<?php include 'footer.php'; ?>


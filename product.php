<?php
/**
 * صفحة تفاصيل المنتج - عرض متقدم
 */
include 'app/config/database.php';
include 'header.php';

// التحقق من وجود ID
if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);

// جلب بيانات المنتج
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo "<div class='not-found-page'><div class='not-found-content'>";
    echo "<i class='fas fa-box-open'></i><h2>عذراً، المنتج غير موجود!</h2>";
    echo "<a href='index.php' class='btn-back-home'><i class='fas fa-home'></i> العودة للرئيسية</a>";
    echo "</div></div>";
    include 'footer.php';
    exit();
}

$product = $result->fetch_assoc();
$stmt->close();

// جلب الصور والفيديوهات باستخدام prepared statements
$images = [];
$img_stmt = $conn->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order");
if ($img_stmt) {
    $img_stmt->bind_param("i", $id);
    $img_stmt->execute();
    $img_result = $img_stmt->get_result();
    while ($img = $img_result->fetch_assoc()) $images[] = $img;
    $img_stmt->close();
}

$videos = [];
$vid_stmt = $conn->prepare("SELECT * FROM product_videos WHERE product_id = ? ORDER BY sort_order");
if ($vid_stmt) {
    $vid_stmt->bind_param("i", $id);
    $vid_stmt->execute();
    $vid_result = $vid_stmt->get_result();
    while ($vid = $vid_result->fetch_assoc()) $videos[] = $vid;
    $vid_stmt->close();
}

// تحليل البيانات
$colors = !empty($product['colors']) ? json_decode($product['colors'], true) : [];
$sizes = !empty($product['sizes']) ? json_decode($product['sizes'], true) : [];
$extra_fields = !empty($product['extra_fields']) ? json_decode($product['extra_fields'], true) : [];

// حساب السعر
$original_price = $product['price'];
$final_price = isset($product['final_price']) ? $product['final_price'] : $original_price;
$discount_type = isset($product['discount_type']) ? $product['discount_type'] : 'none';
$discount_value = isset($product['discount_value']) ? $product['discount_value'] : 0;
$has_discount = ($discount_type != 'none' && $discount_value > 0);
$save_amount = $original_price - $final_price;

// العملة - استخدام النظام الموحد
$currency = getCurrencySymbol();

// حالة المنتج
$product_status = isset($product['status']) ? $product['status'] : 'active';
$product_quantity = isset($product['quantity']) ? intval($product['quantity']) : 99;
// السماح بالشراء إلا إذا كان المنتج مخفي أو نفذت الكمية صراحة
$can_purchase = ($product_status != 'out_of_stock' && $product_status != 'hidden' && $product_status != 'paused') || $product_quantity > 0;
// إذا لم يكن هناك status محدد، اعتبره متوفر
if (empty($product_status) || $product_status == '' || $product_status === null) {
    $can_purchase = true;
}
// دائماً أظهر الأزرار إذا كانت الكمية أكبر من 0
if ($product_quantity > 0) {
    $can_purchase = true;
}
?>

<section class="product-page">
    <div class="product-container">
        <!-- قسم الصور -->
        <div class="product-gallery">
            <div class="main-image-container">
                <?php if ($has_discount): ?>
                <div class="discount-badge">
                    <?php if ($discount_type == 'percentage'): ?>
                        -<?php echo $discount_value; ?>%
                    <?php else: ?>
                        خصم <?php echo number_format($discount_value); ?> <?php echo $currency; ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <img id="main-product-image" src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                
                <div class="image-actions">
                    <button class="img-action-btn" onclick="openFullscreen()" title="تكبير"><i class="fas fa-expand"></i></button>
                    <button class="img-action-btn" onclick="shareProduct()" title="مشاركة"><i class="fas fa-share-alt"></i></button>
                </div>
            </div>
            
            <?php if (!empty($images) || !empty($product['image'])): ?>
            <div class="thumbnail-gallery">
                <div class="thumbnail active" onclick="changeMainImage('<?php echo htmlspecialchars($product['image']); ?>', this)">
                    <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="صورة رئيسية">
                </div>
                <?php foreach ($images as $i => $img): ?>
                <div class="thumbnail" onclick="changeMainImage('<?php echo htmlspecialchars($img['image_url']); ?>', this)">
                    <img src="<?php echo htmlspecialchars($img['image_url']); ?>" alt="صورة <?php echo $i + 2; ?>">
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- قسم المعلومات -->
        <div class="product-info">
            <a href="index.php?cat=<?php echo urlencode($product['category']); ?>" class="product-category-tag">
                <?php echo htmlspecialchars($product['category']); ?>
            </a>
            
            <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
            
            <!-- التقييم -->
            <div class="product-rating">
                <?php $rating = isset($product['rating']) ? $product['rating'] : 0; ?>
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <i class="<?php echo $i <= $rating ? 'fas' : 'far'; ?> fa-star"></i>
                <?php endfor; ?>
                <span class="rating-count">(<?php echo isset($product['rating_count']) ? $product['rating_count'] : 0; ?> تقييم)</span>
            </div>
            
            <!-- السعر -->
            <div class="product-price-section">
                <span class="current-price"><?php echo number_format($final_price); ?> <?php echo $currency; ?></span>
                <?php if ($has_discount): ?>
                    <span class="old-price"><?php echo number_format($original_price); ?> <?php echo $currency; ?></span>
                    <span class="save-badge"><i class="fas fa-tag"></i> وفر <?php echo number_format($save_amount); ?> <?php echo $currency; ?></span>
                <?php endif; ?>
            </div>
            
            <!-- حالة التوفر -->
            <div class="availability-section">
                <?php if ($can_purchase): ?>
                    <div class="availability-status in-stock">
                        <i class="fas fa-check-circle"></i>
                        <span>متوفر في المخزون (<?php echo $product_quantity; ?> قطعة)</span>
                    </div>
                <?php else: ?>
                    <div class="availability-status out-of-stock">
                        <i class="fas fa-times-circle"></i>
                        <span>غير متوفر حالياً</span>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- الشركة المصنعة -->
            <?php if (!empty($product['manufacturer'])): ?>
            <div class="info-box">
                <i class="fas fa-industry"></i>
                <span>الشركة المصنعة: <strong><?php echo htmlspecialchars($product['manufacturer']); ?></strong></span>
            </div>
            <?php endif; ?>

            <!-- الألوان -->
            <?php if (!empty($colors) && is_array($colors)): ?>
            <div class="product-options">
                <h4><i class="fas fa-palette"></i> الألوان المتوفرة:</h4>
                <div class="color-options">
                    <?php foreach ($colors as $color): ?>
                    <button type="button" class="color-btn" onclick="selectOption(this, 'color')" data-color="<?php echo htmlspecialchars($color); ?>">
                        <?php echo htmlspecialchars($color); ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- الأحجام -->
            <?php if (!empty($sizes) && is_array($sizes)): ?>
            <div class="product-options">
                <h4><i class="fas fa-ruler"></i> الأحجام المتوفرة:</h4>
                <div class="size-options">
                    <?php foreach ($sizes as $size): ?>
                    <button type="button" class="size-btn" onclick="selectOption(this, 'size')" data-size="<?php echo htmlspecialchars($size); ?>">
                        <?php echo htmlspecialchars($size); ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- الوصف -->
            <?php if (!empty($product['description'])): ?>
            <div class="product-description">
                <h4><i class="fas fa-align-left"></i> وصف المنتج</h4>
                <div class="description-content"><?php echo nl2br(htmlspecialchars($product['description'])); ?></div>
            </div>
            <?php endif; ?>
            
            <!-- ===== قسم الشراء الرئيسي ===== -->
            <div class="main-purchase-section">
                <div class="purchase-row">
                    <div class="quantity-box">
                        <span class="qty-label">الكمية:</span>
                        <div class="qty-controls">
                            <button type="button" class="qty-btn" onclick="changeQuantity(-1)">-</button>
                            <input type="number" id="product-quantity" value="1" min="1" max="99">
                            <button type="button" class="qty-btn" onclick="changeQuantity(1)">+</button>
                        </div>
                    </div>
                    <button class="main-add-cart-btn" onclick="addToCartWithOptions()">
                        <i class="fas fa-cart-plus"></i>
                        <span>إضافة للسلة</span>
                    </button>
                </div>
                <div class="purchase-buttons">
                    <button class="buy-now-btn" onclick="buyNow()">
                        <i class="fas fa-bolt"></i> شراء الآن
                    </button>
                    <button class="wishlist-btn" id="wishlist-btn" onclick="toggleWishlist(<?php echo $product['id']; ?>)">
                        <i class="far fa-heart"></i>
                    </button>
                </div>
            </div>
            
            <!-- معلومات إضافية -->
            <?php if (!empty($extra_fields) && is_array($extra_fields)): ?>
            <div class="extra-info">
                <h4><i class="fas fa-info-circle"></i> معلومات إضافية</h4>
                <table class="specs-table">
                    <?php foreach ($extra_fields as $key => $value): ?>
                    <tr>
                        <td class="spec-label"><?php echo htmlspecialchars($key); ?></td>
                        <td class="spec-value"><?php echo htmlspecialchars($value); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <?php endif; ?>
            
            <!-- أزرار الشراء القديمة - محذوفة -->
            <div class="purchase-section" style="display:none;">
                <div class="quantity-selector">
                    <label>الكمية:</label>
                    <div class="quantity-controls">
                        <button type="button" onclick="changeQuantity(-1)">-</button>
                        <input type="number" value="1" min="1" max="99">
                        <button type="button" onclick="changeQuantity(1)">+</button>
                    </div>
                </div>
                
                <div class="product-actions">
                    <button class="btn-add-cart" onclick="addToCartWithOptions()">
                        <i class="fas fa-cart-plus"></i> إضافة للسلة
                    </button>
                    <button class="btn-buy-now" onclick="buyNow()">
                        <i class="fas fa-bolt"></i> شراء الآن
                    </button>
                    <button class="btn-wishlist" id="wishlist-btn" onclick="toggleWishlist(<?php echo $product['id']; ?>)">
                        <i class="far fa-heart"></i>
                    </button>
                </div>
            </div>
            
            <!-- ضمانات -->
            <div class="guarantees">
                <div class="guarantee-item"><i class="fas fa-truck"></i><span>توصيل سريع</span></div>
                <div class="guarantee-item"><i class="fas fa-undo"></i><span>إرجاع مجاني</span></div>
                <div class="guarantee-item"><i class="fas fa-shield-alt"></i><span>ضمان الجودة</span></div>
                <div class="guarantee-item"><i class="fas fa-lock"></i><span>دفع آمن</span></div>
            </div>
        </div>
    </div>
</section>

<!-- Fullscreen Modal -->
<div id="fullscreen-modal" class="fullscreen-modal" onclick="closeFullscreen()">
    <button class="close-fullscreen">&times;</button>
    <img id="fullscreen-image" src="">
</div>

<style>
.not-found-page { min-height: 60vh; display: flex; align-items: center; justify-content: center; }
.not-found-content { text-align: center; }
.not-found-content i { font-size: 80px; color: #333; margin-bottom: 20px; }
.not-found-content h2 { color: white; margin-bottom: 20px; }
.btn-back-home { display: inline-flex; align-items: center; gap: 10px; padding: 15px 30px; background: linear-gradient(135deg, #ff3e3e, #ff6b6b); color: white; border-radius: 10px; text-decoration: none; }

.product-page { max-width: 1200px; margin: 0 auto; padding: 30px 20px; }
.product-container { display: grid; grid-template-columns: 1fr 1fr; gap: 50px; }

.product-gallery { position: sticky; top: 100px; height: fit-content; }
.main-image-container { position: relative; background: white; border-radius: 20px; padding: 20px; margin-bottom: 15px; }
.main-image-container img { width: 100%; height: 400px; object-fit: contain; }
.discount-badge { position: absolute; top: 15px; right: 15px; background: linear-gradient(135deg, #ff3e3e, #ff6b6b); color: white; padding: 8px 15px; border-radius: 30px; font-weight: 700; z-index: 10; }
.image-actions { position: absolute; bottom: 15px; left: 15px; display: flex; gap: 10px; }
.img-action-btn { width: 40px; height: 40px; border-radius: 50%; background: rgba(0,0,0,0.5); border: none; color: white; cursor: pointer; }
.img-action-btn:hover { background: #ff3e3e; }

.thumbnail-gallery { display: flex; gap: 10px; overflow-x: auto; }
.thumbnail { width: 80px; height: 80px; background: white; border-radius: 10px; cursor: pointer; border: 3px solid transparent; overflow: hidden; flex-shrink: 0; }
.thumbnail:hover, .thumbnail.active { border-color: #ff3e3e; }
.thumbnail img { width: 100%; height: 100%; object-fit: cover; }

.product-info { padding: 20px 0; }
.product-category-tag { display: inline-block; background: rgba(99, 102, 241, 0.15); color: #6366f1; padding: 6px 16px; border-radius: 20px; font-size: 13px; margin-bottom: 15px; text-decoration: none; }
.product-title { font-size: 28px; color: white; margin-bottom: 15px; }
.product-rating { display: flex; align-items: center; gap: 5px; margin-bottom: 20px; }
.product-rating i { color: #f59e0b; }
.rating-count { color: #888; font-size: 14px; margin-right: 10px; }

.product-price-section { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
.current-price { font-size: 32px; font-weight: 700; color: #10b981; }
.old-price { font-size: 20px; color: #888; text-decoration: line-through; }
.save-badge { background: rgba(16, 185, 129, 0.15); color: #10b981; padding: 6px 14px; border-radius: 20px; font-size: 13px; }

.availability-section { margin-bottom: 20px; }
.availability-status { display: flex; align-items: center; gap: 10px; padding: 12px 18px; border-radius: 10px; font-weight: 600; }
.availability-status.in-stock { background: rgba(16, 185, 129, 0.1); color: #10b981; }
.availability-status.out-of-stock { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

.info-box { background: #1e1e1e; padding: 15px; border-radius: 10px; margin-bottom: 15px; color: #aaa; display: flex; align-items: center; gap: 12px; }
.info-box i { color: #6366f1; }
.info-box strong { color: white; }

.product-options { margin-bottom: 20px; }
.product-options h4 { color: white; font-size: 14px; margin-bottom: 12px; }
.color-options, .size-options { display: flex; gap: 10px; flex-wrap: wrap; }
.color-btn, .size-btn { padding: 10px 20px; background: #1e1e1e; border: 2px solid #333; border-radius: 8px; color: #ccc; cursor: pointer; }
.color-btn:hover, .size-btn:hover, .color-btn.selected, .size-btn.selected { border-color: #ff3e3e; color: #ff3e3e; }

/* قسم الشراء الرئيسي */
.main-purchase-section { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); padding: 25px; border-radius: 20px; margin: 25px 0; border: 2px solid #333; }
.purchase-row { display: flex; align-items: center; gap: 20px; margin-bottom: 15px; flex-wrap: wrap; }
.quantity-box { display: flex; align-items: center; gap: 15px; }
.qty-label { color: white; font-weight: 600; font-size: 16px; }
.qty-controls { display: flex; align-items: center; background: #0d0d1a; border-radius: 12px; overflow: hidden; border: 2px solid #333; }
.qty-btn { width: 45px; height: 45px; background: transparent; border: none; color: white; font-size: 22px; cursor: pointer; transition: all 0.3s; }
.qty-btn:hover { background: #ff3e3e; }
.qty-controls input { width: 60px; height: 45px; background: transparent; border: none; color: white; text-align: center; font-size: 18px; font-weight: 700; }
.main-add-cart-btn { flex: 1; min-width: 200px; padding: 15px 30px; background: linear-gradient(135deg, #ff3e3e, #ff6b6b); color: white; border: none; border-radius: 12px; font-size: 18px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 12px; transition: all 0.3s; box-shadow: 0 5px 20px rgba(255, 62, 62, 0.3); }
.main-add-cart-btn:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(255, 62, 62, 0.5); }
.purchase-buttons { display: flex; gap: 15px; }
.buy-now-btn { flex: 1; padding: 15px 25px; background: linear-gradient(135deg, #10b981, #34d399); color: white; border: none; border-radius: 12px; font-size: 16px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; transition: all 0.3s; }
.buy-now-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4); }
.wishlist-btn { width: 55px; height: 55px; background: #0d0d1a; border: 2px solid #333; border-radius: 12px; color: #888; font-size: 22px; cursor: pointer; transition: all 0.3s; }
.wishlist-btn:hover, .wishlist-btn.liked { border-color: #ff3e3e; color: #ff3e3e; background: rgba(255, 62, 62, 0.1); }
</style>

<style>
.product-description { background: #1e1e1e; padding: 20px; border-radius: 15px; margin-bottom: 20px; }
.product-description h4 { color: white; margin-bottom: 15px; }
.description-content { color: #aaa; line-height: 1.8; }

.extra-info { margin-bottom: 20px; }
.extra-info h4 { color: white; margin-bottom: 15px; }
.specs-table { width: 100%; background: #1e1e1e; border-radius: 10px; overflow: hidden; }
.specs-table tr:nth-child(even) { background: #252525; }
.specs-table td { padding: 14px 18px; }
.spec-label { color: white; font-weight: 600; width: 40%; }
.spec-value { color: #aaa; }

.purchase-section { margin-top: 25px; padding-top: 25px; border-top: 1px solid #333; }
.quantity-selector { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; }
.quantity-selector label { color: white; font-weight: 600; }
.quantity-controls { display: flex; align-items: center; background: #1e1e1e; border-radius: 10px; overflow: hidden; }
.quantity-controls button { width: 45px; height: 45px; background: transparent; border: none; color: white; font-size: 20px; cursor: pointer; }
.quantity-controls button:hover { background: #ff3e3e; }
.quantity-controls input { width: 60px; height: 45px; background: transparent; border: none; color: white; text-align: center; font-size: 16px; font-weight: 600; }

.product-actions { display: flex; gap: 15px; flex-wrap: wrap; }
.btn-add-cart { flex: 1; min-width: 200px; padding: 18px 30px; background: linear-gradient(135deg, #ff3e3e, #ff6b6b); color: white; border: none; border-radius: 15px; font-size: 16px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; }
.btn-add-cart:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(255, 62, 62, 0.4); }
.btn-buy-now { flex: 1; min-width: 150px; padding: 18px 30px; background: linear-gradient(135deg, #10b981, #34d399); color: white; border: none; border-radius: 15px; font-size: 16px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; }
.btn-buy-now:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(16, 185, 129, 0.4); }
.btn-wishlist { width: 60px; height: 60px; background: #1e1e1e; border: 2px solid #333; border-radius: 15px; color: #888; font-size: 24px; cursor: pointer; }
.btn-wishlist:hover, .btn-wishlist.liked { border-color: #ff3e3e; color: #ff3e3e; }
.btn-notify { flex: 1; padding: 18px 30px; background: #333; color: #888; border: none; border-radius: 15px; font-size: 16px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; }

.guarantees { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-top: 25px; padding-top: 25px; border-top: 1px solid #333; }
.guarantee-item { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 15px; background: #1e1e1e; border-radius: 10px; text-align: center; }
.guarantee-item i { font-size: 24px; color: #6366f1; }
.guarantee-item span { color: #aaa; font-size: 12px; }

.fullscreen-modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.95); z-index: 9999; align-items: center; justify-content: center; }
.fullscreen-modal.active { display: flex; }
.fullscreen-modal img { max-width: 90%; max-height: 90%; object-fit: contain; }
.close-fullscreen { position: absolute; top: 20px; right: 20px; width: 50px; height: 50px; background: rgba(255,255,255,0.1); border: none; color: white; font-size: 30px; border-radius: 50%; cursor: pointer; }

@media (max-width: 768px) {
    .product-container { grid-template-columns: 1fr; gap: 30px; }
    .product-gallery { position: static; }
    .main-image-container img { height: 300px; }
    .product-title { font-size: 22px; }
    .current-price { font-size: 26px; }
    .product-actions { flex-direction: column; }
    .btn-add-cart, .btn-buy-now { min-width: 100%; }
    .btn-wishlist { width: 100%; height: 50px; }
    .guarantees { grid-template-columns: repeat(2, 1fr); }
}
</style>

<script>
// بيانات المنتج
var productData = {
    id: <?php echo (int)$product['id']; ?>,
    name: <?php echo json_encode($product['name'], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>,
    price: <?php echo (float)$final_price; ?>,
    image: <?php echo json_encode($product['image'], JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    maxQuantity: <?php echo (int)$product_quantity; ?>
};

var selectedColor = null;
var selectedSize = null;

// تغيير الصورة الرئيسية
function changeMainImage(url, element) {
    document.getElementById('main-product-image').src = url;
    var thumbs = document.querySelectorAll('.thumbnail');
    for (var i = 0; i < thumbs.length; i++) {
        thumbs[i].classList.remove('active');
    }
    element.classList.add('active');
}

// اختيار الخيارات
function selectOption(btn, type) {
    var container = btn.parentElement;
    var buttons = container.querySelectorAll('button');
    for (var i = 0; i < buttons.length; i++) {
        buttons[i].classList.remove('selected');
    }
    btn.classList.add('selected');
    
    if (type === 'color') {
        selectedColor = btn.getAttribute('data-color');
    } else if (type === 'size') {
        selectedSize = btn.getAttribute('data-size');
    }
}

// تغيير الكمية
function changeQuantity(delta) {
    var input = document.getElementById('product-quantity');
    var value = parseInt(input.value) + delta;
    if (value < 1) value = 1;
    if (value > productData.maxQuantity) value = productData.maxQuantity;
    input.value = value;
}

// إضافة للسلة
function addToCartWithOptions() {
    var quantityInput = document.getElementById('product-quantity');
    var quantity = quantityInput ? parseInt(quantityInput.value) : 1;
    if (isNaN(quantity) || quantity < 1) quantity = 1;
    
    var userKey = (typeof activeUserId !== 'undefined') ? activeUserId : 'guest';
    var cartKey = 'cart_' + userKey;
    
    try {
        var cartStr = localStorage.getItem(cartKey);
        var cart = cartStr ? JSON.parse(cartStr) : [];
        
        var itemName = productData.name;
        var found = false;
        
        for (var i = 0; i < cart.length; i++) {
            if (cart[i].name === itemName) {
                cart[i].quantity += quantity;
                found = true;
                break;
            }
        }
        
        if (!found) {
            cart.push({
                name: productData.name,
                price: productData.price,
                image: productData.image,
                quantity: quantity,
                color: selectedColor,
                size: selectedSize
            });
        }
        
        localStorage.setItem(cartKey, JSON.stringify(cart));
        updateCartCounter();
        showToast('تمت إضافة ' + quantity + ' قطعة للسلة ✅');
    } catch (e) {
        console.error('Error:', e);
        alert('تمت الإضافة للسلة');
    }
}

// شراء الآن
function buyNow() {
    addToCartWithOptions();
    window.location.href = 'cart.php';
}

// المفضلة
function toggleWishlist(id) {
    var btn = document.getElementById('wishlist-btn');
    if (btn.classList.contains('liked')) {
        btn.classList.remove('liked');
        btn.innerHTML = '<i class="far fa-heart"></i>';
        showToast('تم الحذف من المفضلة');
    } else {
        btn.classList.add('liked');
        btn.innerHTML = '<i class="fas fa-heart"></i>';
        showToast('تمت الإضافة للمفضلة ❤️');
    }
}

// تكبير الصورة
function openFullscreen() {
    var modal = document.getElementById('fullscreen-modal');
    var img = document.getElementById('fullscreen-image');
    img.src = document.getElementById('main-product-image').src;
    modal.classList.add('active');
}

function closeFullscreen() {
    document.getElementById('fullscreen-modal').classList.remove('active');
}

// مشاركة
function shareProduct() {
    if (navigator.share) {
        navigator.share({ title: productData.name, url: window.location.href });
    } else {
        navigator.clipboard.writeText(window.location.href);
        showToast('تم نسخ الرابط 📋');
    }
}

// إشعار
function notifyMe() {
    showToast('سيتم إشعارك عند توفر المنتج 🔔');
}

// رسالة
function showToast(message) {
    var toast = document.getElementById('toast');
    if (toast) {
        toast.textContent = message;
        toast.className = 'show';
        setTimeout(function() { toast.className = ''; }, 3000);
    } else {
        alert(message);
    }
}

// تحديث عداد السلة
function updateCartCounter() {
    try {
        var userKey = (typeof activeUserId !== 'undefined') ? activeUserId : 'guest';
        var cartStr = localStorage.getItem('cart_' + userKey);
        var cart = cartStr ? JSON.parse(cartStr) : [];
        var count = 0;
        for (var i = 0; i < cart.length; i++) {
            count += (cart[i].quantity || 1);
        }
        var counter = document.getElementById('cart-count');
        if (counter) counter.textContent = count;
    } catch (e) {}
}

// ESC لإغلاق الصورة
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeFullscreen();
});
</script>

<?php include 'footer.php'; ?>

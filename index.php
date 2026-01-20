<?php
/**
 * ===========================================
 * الصفحة الرئيسية للمتجر
 * ===========================================
 * 
 * تعرض:
 * - سلايدر العروض
 * - الفئات الرئيسية
 * - المنتجات المميزة
 * - أحدث المنتجات
 * 
 * @package MyShop
 * @version 3.0
 */

include 'app/config/database.php';
include 'header.php';
?>



<!-- ========================================
     السلايدر الرئيسي (يُحمّل من قاعدة البيانات)
     ======================================== -->
<?php
// جلب البانرات النشطة مع التحقق من وجود الجدول
$banners = [];
try {
    $banners_result = $conn->query("SELECT * FROM banners WHERE is_active = 1 ORDER BY sort_order, id");
    if ($banners_result && $banners_result->num_rows > 0) {
        while ($b = $banners_result->fetch_assoc()) {
            $banners[] = $b;
        }
    }
} catch (Exception $e) {
    // في حالة عدم وجود الجدول أو أي خطأ، نستخدم البانرات الافتراضية
    error_log("Banner query error: " . $e->getMessage());
}
// إذا لم توجد بانرات، استخدم بانرات افتراضية
if (empty($banners)) {
    $banners = [
        ['title' => 'عروض الجمعة البيضاء', 'subtitle' => 'خصومات تصل إلى 70% على جميع الإلكترونيات', 'badge_text' => '🔥 عرض حصري', 'badge_color' => 'red', 'button_text' => 'تسوق الآن', 'button_link' => 'index.php?cat=electronics', 'image_url' => 'https://pngimg.com/uploads/headphones/headphones_PNG7645.png', 'bg_gradient' => 'linear-gradient(135deg, #1a1a2e 0%, #16213e 100%)'],
        ['title' => 'تشكيلة الشتاء 2025', 'subtitle' => 'أحدث صيحات الموضة والأزياء الشتوية', 'badge_text' => '✨ جديد', 'badge_color' => 'blue', 'button_text' => 'اكتشف المزيد', 'button_link' => 'index.php?cat=fashion', 'image_url' => 'https://pngimg.com/uploads/hoodie/hoodie_PNG48.png', 'bg_gradient' => 'linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%)'],
        ['title' => 'ساعات فاخرة', 'subtitle' => 'مجموعة حصرية من أفخم الساعات العالمية', 'badge_text' => '🎁 توصيل مجاني', 'badge_color' => 'green', 'button_text' => 'تصفح الآن', 'button_link' => 'index.php?cat=watches', 'image_url' => 'https://pngimg.com/uploads/watches/watches_PNG9864.png', 'bg_gradient' => 'linear-gradient(135deg, #134e5e 0%, #71b280 100%)']
    ];
}
$totalBanners = count($banners);
?>
<section class="hero-slider">
    <div class="slider-container">
        <?php foreach ($banners as $index => $banner): ?>
        <a href="<?php echo htmlspecialchars($banner['button_link'] ?? '#'); ?>" 
           class="slide <?php echo $index === 0 ? 'active' : ''; ?>" 
           style="background: <?php echo htmlspecialchars($banner['bg_gradient']); ?>; text-decoration: none;"
           onclick="if(this.href === '#' || this.href === window.location.href + '#') { event.preventDefault(); }">
            <div class="slide-content">
                <?php if (!empty($banner['badge_text'])): ?>
                <span class="slide-badge <?php echo $banner['badge_color'] ?? ''; ?>"><?php echo htmlspecialchars($banner['badge_text']); ?></span>
                <?php endif; ?>
                <h1><?php echo htmlspecialchars($banner['title']); ?></h1>
                <p><?php echo htmlspecialchars($banner['subtitle']); ?></p>
                <?php if (!empty($banner['button_link'])): ?>
                <span class="slide-btn">
                    <?php echo htmlspecialchars($banner['button_text'] ?? 'تسوق الآن'); ?>
                    <i class="fas fa-arrow-left"></i>
                </span>
                <?php endif; ?>
            </div>
            <?php if (!empty($banner['image_url'])): ?>
            <img src="<?php echo htmlspecialchars($banner['image_url']); ?>" class="slide-image" alt="<?php echo htmlspecialchars($banner['title']); ?>">
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
        
        <!-- أزرار التنقل -->
        <?php if ($totalBanners > 1): ?>
        <button class="slider-nav slider-prev" onclick="changeSlide(1)">
            <i class="fas fa-chevron-right"></i>
        </button>
        <button class="slider-nav slider-next" onclick="changeSlide(-1)">
            <i class="fas fa-chevron-left"></i>
        </button>
        <?php endif; ?>
    </div>
    
    <!-- نقاط التنقل -->
    <?php if ($totalBanners > 1): ?>
    <div class="slider-dots">
        <?php for ($i = 1; $i <= $totalBanners; $i++): ?>
        <span class="dot <?php echo $i === 1 ? 'active' : ''; ?>" onclick="goToSlide(<?php echo $i; ?>)"></span>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</section>

<!-- ========================================
     قسم الفئات - تصميم دائري أنيق
     ======================================== -->
<?php
// جلب الفئات النشطة من قاعدة البيانات
$categories_list = [];
$default_images = [
    'electronics' => 'https://images.unsplash.com/photo-1550009158-9ebf69173e03?w=150',
    'fashion' => 'https://images.unsplash.com/photo-1617137968427-85924c800a22?w=150',
    'women' => 'https://images.unsplash.com/photo-1550614000-4b9519e02d37?w=150',
    'watches' => 'https://images.unsplash.com/photo-1524592094714-0f0654e20314?w=150',
    'perfume' => 'https://images.unsplash.com/photo-1594035910387-fea477942698?w=150',
    'shoes' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=150',
    'bags' => 'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=150',
    'accessories' => 'https://images.unsplash.com/photo-1611923134239-b9be5816e23c?w=150'
];
$default_image = 'https://images.unsplash.com/photo-1472851294608-062f824d29cc?w=150';

try {
    $cat_result = $conn->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order, name");
    if ($cat_result && $cat_result->num_rows > 0) {
        while ($cat = $cat_result->fetch_assoc()) {
            $categories_list[] = $cat;
        }
    }
} catch (Exception $e) {
    error_log("Categories query error: " . $e->getMessage());
}
?>
<section class="categories-section">
    <div class="section-header">
        <h2 class="section-title">
            <i class="fas fa-th-large"></i>
            تسوق حسب الفئة
        </h2>
    </div>
    
    <div class="categories-scroll-wrapper">
        <div class="categories-scroll" id="categoriesScroll">
            <?php if (!empty($categories_list)): ?>
                <?php foreach ($categories_list as $category): 
                    // استخدام صورة الفئة إذا وجدت، وإلا صورة افتراضية حسب الـ slug
                    $cat_image = !empty($category['image_url']) ? $category['image_url'] : 
                                 ($default_images[$category['slug']] ?? $default_image);
                ?>
                <a href="index.php?cat=<?php echo htmlspecialchars($category['slug']); ?>" class="category-circle">
                    <div class="circle-img">
                        <img src="<?php echo htmlspecialchars($cat_image); ?>" alt="<?php echo htmlspecialchars($category['name']); ?>">
                    </div>
                    <span><?php echo htmlspecialchars($category['name']); ?></span>
                </a>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- فئات افتراضية في حالة عدم وجود فئات في قاعدة البيانات -->
                <a href="index.php?cat=electronics" class="category-circle">
                    <div class="circle-img">
                        <img src="https://images.unsplash.com/photo-1550009158-9ebf69173e03?w=150" alt="إلكترونيات">
                    </div>
                    <span>إلكترونيات</span>
                </a>
                <a href="index.php?cat=fashion" class="category-circle">
                    <div class="circle-img">
                        <img src="https://images.unsplash.com/photo-1617137968427-85924c800a22?w=150" alt="أزياء">
                    </div>
                    <span>أزياء</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ========================================
     قسم المنتجات
     ======================================== -->
<section class="products-section">
    <div class="section-header">
        <h2 class="section-title">
            <i class="fas fa-fire"></i>
            <?php 
            if (isset($_GET['search'])) {
                echo 'نتائج البحث: ' . htmlspecialchars($_GET['search']);
            } elseif (isset($_GET['cat'])) {
                echo 'منتجات: ' . htmlspecialchars($_GET['cat']);
            } else {
                echo 'أحدث المنتجات';
            }
            ?>
        </h2>
    </div>
    
    <div class="products-grid">
        <?php
        // بناء الاستعلام - جلب المنتجات المرئية فقط
        $sql = "SELECT id, name, price, image, category, final_price, discount_type, discount_value, status, quantity, rating, currency FROM products WHERE is_visible = 1 AND status != 'hidden'";
        $params = [];
        $types = "";
        
        if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
            $search_term = "%" . trim($_GET['search']) . "%";
            $sql .= " AND name LIKE ?";
            $params[] = $search_term;
            $types .= "s";
        } elseif (isset($_GET['cat']) && !empty(trim($_GET['cat']))) {
            $sql .= " AND category = ?";
            $params[] = trim($_GET['cat']);
            $types .= "s";
        }
        
        $sql .= " ORDER BY id DESC LIMIT 12";
        
        $stmt = $conn->prepare($sql);
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        // استخدام العملة الموحدة من النظام
        $currency = getCurrencySymbol();

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // استخدام json_encode لتأمين القيم في JavaScript
                $jsName  = json_encode($row['name'], JSON_HEX_APOS | JSON_HEX_QUOT);
                $jsImage = json_encode($row['image'], JSON_HEX_APOS | JSON_HEX_QUOT);
                $finalPrice = $row['final_price'] ?? $row['price'];
                $jsPrice = (float)$finalPrice;
                $hasDiscount = ($row['discount_type'] ?? 'none') != 'none' && ($row['discount_value'] ?? 0) > 0;
                $isOutOfStock = ($row['status'] == 'out_of_stock' || ($row['quantity'] ?? 0) == 0);
                $rating = $row['rating'] ?? 0;
                ?>
                
                <div class="product-card <?php echo $isOutOfStock ? 'out-of-stock' : ''; ?>" onclick="window.location.href='product.php?id=<?php echo (int)$row['id']; ?>'" style="cursor: pointer;">
                    <!-- صورة المنتج -->
                    <div class="product-image">
                        <!-- الشارات -->
                        <div class="product-badges">
                            <?php if ($hasDiscount): ?>
                                <span class="badge-discount">-<?php echo $row['discount_value']; ?>%</span>
                            <?php endif; ?>
                            <?php if ($isOutOfStock): ?>
                                <span class="badge-out">نفذ</span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- أزرار التفاعل -->
                        <div class="product-actions">
                            <button 
                                class="action-btn wishlist-btn" 
                                onclick="event.stopPropagation(); toggleWishlist(<?php echo (int)$row['id']; ?>, <?php echo $jsName; ?>, <?php echo $jsImage; ?>, <?php echo $jsPrice; ?>)"
                                title="إضافة للمفضلة"
                            >
                                <i class="far fa-heart"></i>
                            </button>
                            <button class="action-btn" onclick="event.stopPropagation(); window.location.href='product.php?id=<?php echo (int)$row['id']; ?>'" title="عرض سريع">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                        
                        <img 
                            src="<?php echo htmlspecialchars($row['image']); ?>" 
                            alt="<?php echo htmlspecialchars($row['name']); ?>"
                            loading="lazy"
                        >
                    </div>
                    
                    <!-- معلومات المنتج -->
                    <div class="product-info">
                        <div class="product-category"><?php echo htmlspecialchars($row['category']); ?></div>
                        
                        <h3 class="product-name"><?php echo htmlspecialchars($row['name']); ?></h3>
                        
                        <!-- التقييم -->
                        <div class="product-rating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="<?php echo $i <= $rating ? 'fas' : 'far'; ?> fa-star"></i>
                            <?php endfor; ?>
                            <span>(<?php echo number_format($rating, 1); ?>)</span>
                        </div>
                        
                        <!-- السعر وزر الشراء -->
                        <div class="product-footer">
                            <div class="product-price">
                                <span class="current-price">
                                    <?php echo number_format($finalPrice, 0); ?>
                                    <small><?php echo $currency; ?></small>
                                </span>
                                <?php if ($hasDiscount): ?>
                                    <span class="old-price"><?php echo number_format($row['price'], 0); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!$isOutOfStock): ?>
                            <button 
                                class="add-to-cart-btn" 
                                onclick="event.stopPropagation(); addToCart(<?php echo $jsName; ?>, <?php echo $jsPrice; ?>, <?php echo $jsImage; ?>)"
                                title="إضافة للسلة"
                            >
                                <i class="fas fa-cart-plus"></i>
                            </button>
                            <?php else: ?>
                            <span class="out-of-stock-label">نفذ</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php
            }
        } else {
            ?>
            <div class="no-products" style="grid-column: 1 / -1;">
                <i class="fas fa-box-open"></i>
                <h3>لا توجد منتجات</h3>
                <p>عذراً، لم نجد منتجات تطابق بحثك. جرب كلمات أخرى.</p>
            </div>
            <?php
        }
        
        $stmt->close();
        ?>
    </div>
</section>

<!-- ========================================
     بانر العروض
     ======================================== -->
<section class="promo-banner">
    <div class="banner-content">
        <div class="banner-text">
            <h2>🎉 خصم 30% على طلبك الأول</h2>
            <p>سجل الآن واحصل على كود خصم حصري يصل إلى 30% على أول طلب لك</p>
            <a href="register.php" class="banner-btn">
                سجل مجاناً
                <i class="fas fa-arrow-left"></i>
            </a>
        </div>
        <img src="https://pngimg.com/uploads/gift/gift_PNG5950.png" class="banner-image" alt="هدية">
    </div>
</section>

<!-- ========================================
     سكريبت السلايدر
     ======================================== -->
<script>
// ✅ استخدام var بدل let لتجنب التكرار
var heroCurrentSlide = 1;
var heroSlideInterval;
var heroTotalSlides = <?php echo $totalBanners; ?>;

// بدء السلايدر التلقائي
function startSlider() {
    heroSlideInterval = setInterval(() => {
        changeSlide(1);
    }, 5000);
}

// تغيير الشريحة
function changeSlide(direction) {
    heroCurrentSlide += direction;
    
    if (heroCurrentSlide > heroTotalSlides) heroCurrentSlide = 1;
    if (heroCurrentSlide < 1) heroCurrentSlide = heroTotalSlides;
    
    updateSlider();
    resetHeroInterval();
}

// الذهاب لشريحة محددة
function goToSlide(n) {
    heroCurrentSlide = n;
    updateSlider();
    resetHeroInterval();
}

// تحديث العرض
function updateSlider() {
    // إخفاء جميع الشرائح
    document.querySelectorAll('.slide').forEach((slide, index) => {
        slide.classList.remove('active');
        if (index === heroCurrentSlide - 1) {
            slide.classList.add('active');
        }
    });
    
    // تحديث النقاط
    document.querySelectorAll('.dot').forEach((dot, index) => {
        dot.classList.remove('active');
        if (index === heroCurrentSlide - 1) {
            dot.classList.add('active');
        }
    });
}

// إعادة ضبط المؤقت
function resetHeroInterval() {
    clearInterval(heroSlideInterval);
    startSlider();
}

// بدء السلايدر عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    startSlider();
    
    // دعم السحب على الموبايل والكمبيوتر
    const slider = document.querySelector('.slider-container');
    if (!slider) return;
    
    let startX = 0;
    let endX = 0;
    let startY = 0;
    let endY = 0;
    let isDragging = false;
    let hasMoved = false;
    const SWIPE_THRESHOLD = 30;
    
    // Touch events للموبايل
    slider.addEventListener('touchstart', e => {
        startX = e.changedTouches[0].screenX;
        startY = e.changedTouches[0].screenY;
        isDragging = true;
        hasMoved = false;
        clearInterval(heroSlideInterval);
    }, { passive: true });
    
    slider.addEventListener('touchmove', e => {
        if (!isDragging) return;
        endX = e.changedTouches[0].screenX;
        endY = e.changedTouches[0].screenY;
        
        // تحقق من الحركة الأفقية
        const diffX = Math.abs(startX - endX);
        const diffY = Math.abs(startY - endY);
        
        if (diffX > 10 || diffY > 10) {
            hasMoved = true;
        }
    }, { passive: true });
    
    slider.addEventListener('touchend', e => {
        if (!isDragging) return;
        endX = e.changedTouches[0].screenX;
        
        const diff = startX - endX;
        
        // إذا كان سحب أفقي كافي
        if (Math.abs(diff) > SWIPE_THRESHOLD && hasMoved) {
            // التحقق من إمكانية إلغاء الحدث قبل استدعاء preventDefault
            if (e.cancelable) {
                e.preventDefault();
            }
            if (diff > 0) {
                changeSlide(-1);
            } else {
                changeSlide(1);
            }
        }
        
        isDragging = false;
        hasMoved = false;
        startSlider();
    }, { passive: false });
    
    // Mouse events للكمبيوتر
    slider.addEventListener('mousedown', e => {
        startX = e.screenX;
        isDragging = true;
        hasMoved = false;
        slider.style.cursor = 'grabbing';
        clearInterval(heroSlideInterval);
    });
    
    slider.addEventListener('mousemove', e => {
        if (!isDragging) return;
        endX = e.screenX;
        if (Math.abs(startX - endX) > 5) {
            hasMoved = true;
        }
    });
    
    slider.addEventListener('mouseup', e => {
        if (!isDragging) return;
        endX = e.screenX;
        
        const diff = startX - endX;
        if (Math.abs(diff) > SWIPE_THRESHOLD && hasMoved) {
            e.preventDefault();
            if (diff > 0) {
                changeSlide(-1);
            } else {
                changeSlide(1);
            }
        }
        
        isDragging = false;
        hasMoved = false;
        slider.style.cursor = 'grab';
        startSlider();
    });
    
    slider.addEventListener('mouseleave', () => {
        if (isDragging) {
            isDragging = false;
            hasMoved = false;
            slider.style.cursor = 'grab';
            startSlider();
        }
    });
    
    // منع النقر على الرابط أثناء السحب
    slider.querySelectorAll('.slide').forEach(slide => {
        slide.addEventListener('click', e => {
            if (hasMoved) {
                e.preventDefault();
            }
        });
    });
});

// ✅ الدوال موجودة في script.js - لا نكررها هنا

// ========================================
// سكريبت التمرير التلقائي للفئات
// ========================================
(function() {
    const categoriesScroll = document.getElementById('categoriesScroll');
    if (!categoriesScroll) return;
    
    let catScrollInterval;
    let catScrollDirection = 1;
    let catIsDragging = false;
    let catStartX = 0;
    
    // التمرير التلقائي
    function startCategoriesAutoScroll() {
        catScrollInterval = setInterval(() => {
            if (catIsDragging) return;
            
            const maxScroll = categoriesScroll.scrollWidth - categoriesScroll.clientWidth;
            const currentScroll = categoriesScroll.scrollLeft;
            
            // تغيير الاتجاه عند الوصول للنهاية
            if (currentScroll >= maxScroll - 5) {
                catScrollDirection = -1;
            } else if (currentScroll <= 5) {
                catScrollDirection = 1;
            }
            
            categoriesScroll.scrollLeft += catScrollDirection * 1;
        }, 30);
    }
    
    function stopCategoriesAutoScroll() {
        clearInterval(catScrollInterval);
    }
    
    // بدء التمرير التلقائي
    startCategoriesAutoScroll();
    
    // إيقاف عند التفاعل
    categoriesScroll.addEventListener('mouseenter', stopCategoriesAutoScroll);
    categoriesScroll.addEventListener('mouseleave', () => {
        if (!catIsDragging) startCategoriesAutoScroll();
    });
    
    // دعم السحب باللمس
    categoriesScroll.addEventListener('touchstart', e => {
        catIsDragging = true;
        catStartX = e.touches[0].pageX - categoriesScroll.offsetLeft;
        stopCategoriesAutoScroll();
    }, { passive: true });
    
    categoriesScroll.addEventListener('touchmove', e => {
        if (!catIsDragging) return;
        const x = e.touches[0].pageX - categoriesScroll.offsetLeft;
        const walk = (catStartX - x) * 1.5;
        categoriesScroll.scrollLeft += walk;
        catStartX = x;
    }, { passive: true });
    
    categoriesScroll.addEventListener('touchend', () => {
        catIsDragging = false;
        setTimeout(startCategoriesAutoScroll, 2000);
    });
    
    // دعم السحب بالماوس
    categoriesScroll.addEventListener('mousedown', e => {
        catIsDragging = true;
        catStartX = e.pageX - categoriesScroll.offsetLeft;
        categoriesScroll.style.cursor = 'grabbing';
        stopCategoriesAutoScroll();
    });
    
    categoriesScroll.addEventListener('mousemove', e => {
        if (!catIsDragging) return;
        e.preventDefault();
        const x = e.pageX - categoriesScroll.offsetLeft;
        const walk = (catStartX - x) * 1.5;
        categoriesScroll.scrollLeft += walk;
        catStartX = x;
    });
    
    categoriesScroll.addEventListener('mouseup', () => {
        catIsDragging = false;
        categoriesScroll.style.cursor = 'grab';
        setTimeout(startCategoriesAutoScroll, 2000);
    });
    
    categoriesScroll.addEventListener('mouseleave', () => {
        if (catIsDragging) {
            catIsDragging = false;
            categoriesScroll.style.cursor = 'grab';
        }
    });
    
    // تعيين cursor
    categoriesScroll.style.cursor = 'grab';
})();
</script>

<?php include 'footer.php'; ?>

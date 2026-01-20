<?php
/**
 * ===========================================
 * ملف الهيدر الرئيسي - متجاوب مع جميع الأجهزة
 * ===========================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تحميل إعدادات العملة إذا لم تكن محمّلة
if (!isset($CURRENT_CURRENCY)) {
    include_once __DIR__ . '/app/config/currency.php';
}

// تحميل إعدادات المتجر
if (!defined('SETTINGS_LOADED')) {
    include_once __DIR__ . '/app/config/database.php';
    include_once __DIR__ . '/app/config/settings.php';
    if (isset($conn)) {
        loadAllSettings($conn);
    }
}

// التحقق من وضع الصيانة
if (function_exists('getSetting') && getSetting('store_status') === 'maintenance') {
    // السماح للمسؤولين بالدخول
    if (!isset($_SESSION['admin_id'])) {
        $maintenance_message = getSetting('maintenance_message', 'المتجر تحت الصيانة حالياً');
        include_once __DIR__ . '/maintenance.php';
        exit;
    }
}

// الحصول على اسم المتجر
$store_name = function_exists('getSetting') ? getSetting('store_name', 'مشترياتي') : 'مشترياتي';
$store_logo = function_exists('getSetting') ? getSetting('store_logo', '') : '';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($store_name); ?></title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="public/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    
    <!-- Header Styles -->

    
    <script>
        const activeUserId = "<?php echo isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 'guest'; ?>";
        
        // إعدادات العملة الموحدة للمتجر
        const CURRENCY = {
            code: "<?php echo isset($CURRENT_CURRENCY) ? $CURRENT_CURRENCY['code'] : 'SAR'; ?>",
            symbol: "<?php echo isset($CURRENT_CURRENCY) ? $CURRENT_CURRENCY['symbol'] : 'ر.س'; ?>",
            position: "<?php echo isset($CURRENT_CURRENCY) ? $CURRENT_CURRENCY['position'] : 'after'; ?>",
            decimals: <?php echo isset($CURRENT_CURRENCY) ? $CURRENT_CURRENCY['decimals'] : 2; ?>,
            taxRate: <?php echo isset($CURRENT_CURRENCY) ? $CURRENT_CURRENCY['tax_rate'] : 0.15; ?>
        };
        
        // دالة تنسيق السعر
        function formatPrice(price, showSymbol = true) {
            const formatted = price.toFixed(CURRENCY.decimals);
            if (!showSymbol) return formatted;
            
            if (CURRENCY.position === 'before') {
                return CURRENCY.symbol + ' ' + formatted;
            } else {
                return formatted + ' ' + CURRENCY.symbol;
            }
        }
    </script>
</head>
<body>

    <!-- ========== الهيدر ========== -->
    <header class="main-header" id="mainHeader">
        <div class="header-container">
            
            <!-- زر القائمة للموبايل (يظهر أولاً في الموبايل) -->
            <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="القائمة">
                <span></span>
                <span></span>
                <span></span>
            </button>
            
            <!-- شريط البحث -->
            <div class="search-container">
                <form action="index.php" method="GET" class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input 
                        type="text" 
                        name="search" 
                        placeholder="ابحث..." 
                        value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                        autocomplete="off"
                    >
                </form>
            </div>
            
            <!-- القسم الأيسر: الشعار + السلة + الإشعارات -->
            <div class="header-left-section">
                
                <?php if (isset($_SESSION['user_id'])): ?>
                <!-- الإشعارات -->
                <div class="notification-wrapper">
                    <a href="#" class="nav-icon" id="notificationBell" title="الإشعارات">
                        <i class="fas fa-bell"></i>
                        <span class="badge" id="notificationBadge" style="display:none;">0</span>
                    </a>
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-header">
                            <h4>الإشعارات</h4>
                            <button id="markAllRead" class="mark-all-btn">تحديد الكل كمقروء</button>
                        </div>
                        <div class="notification-list" id="notificationList">
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- السلة -->
                <a href="cart.php" class="nav-icon cart-icon" title="سلة المشتريات">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="badge" id="cart-count">0</span>
                </a>
                
                <!-- الشعار -->
                <a href="index.php" class="logo-area">
                    <?php if ($store_logo): ?>
                        <img src="<?php echo htmlspecialchars($store_logo); ?>" alt="<?php echo htmlspecialchars($store_name); ?>" class="logo-img" style="height: 40px;">
                    <?php else: ?>
                        <div class="logo-icon">
                            <i class="fas fa-shopping-bag"></i>
                        </div>
                    <?php endif; ?>
                    <div class="logo-text"><?php echo htmlspecialchars($store_name); ?><span>.</span></div>
                </a>
            </div>
            
            <!-- أيقونات التنقل للشاشات الكبيرة -->
            <div class="nav-icons desktop-only">
                
                <!-- التواصل -->
                <a href="contact.php" class="nav-icon" title="تواصل معنا">
                    <i class="fas fa-headset"></i>
                </a>
                
                <?php if (isset($_SESSION['user_id'])): ?>
                <!-- الإشعارات للشاشات الكبيرة -->
                <div class="notification-wrapper">
                    <a href="#" class="nav-icon notification-bell-desktop" title="الإشعارات">
                        <i class="fas fa-bell"></i>
                        <span class="badge notification-badge-desktop" style="display:none;">0</span>
                    </a>
                    <div class="notification-dropdown notification-dropdown-desktop">
                        <div class="notification-header">
                            <h4>الإشعارات</h4>
                            <button class="mark-all-btn mark-all-desktop">تحديد الكل كمقروء</button>
                        </div>
                        <div class="notification-list notification-list-desktop">
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- المفضلة -->
                <a href="wishlist.php" class="nav-icon" title="المفضلة">
                    <i class="far fa-heart"></i>
                    <span class="badge" id="wishlist-count">0</span>
                </a>
                
                <!-- السلة للشاشات الكبيرة -->
                <a href="cart.php" class="nav-icon cart-icon" title="سلة المشتريات">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="badge cart-count-desktop">0</span>
                </a>
                
                <!-- قائمة المستخدم -->
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="user-menu">
                        <div class="user-trigger">
                            <img 
                                src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['user_name']); ?>&background=ff3e3e&color=fff&bold=true&size=70" 
                                alt="الصورة الشخصية"
                                class="user-avatar"
                            >
                            <span class="user-name"><?php echo htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]); ?></span>
                            <i class="fas fa-chevron-down" style="color:#888; font-size:12px;"></i>
                        </div>
                        <div class="user-dropdown">
                            <div class="dropdown-header">
                                <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong>
                                <small>مرحباً بعودتك!</small>
                            </div>
                            <a href="my_orders.php" class="dropdown-item">
                                <i class="fas fa-box"></i>
                                <span>طلباتي</span>
                            </a>
                            <a href="track.php" class="dropdown-item">
                                <i class="fas fa-truck"></i>
                                <span>تتبع الشحنات</span>
                            </a>
                            <a href="wishlist.php" class="dropdown-item">
                                <i class="fas fa-heart"></i>
                                <span>المفضلة</span>
                            </a>
                            <a href="logout.php" class="dropdown-item logout">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>تسجيل الخروج</span>
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="login-btn">
                        <i class="fas fa-user"></i>
                        <span>دخول</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- ========== القائمة الجانبية للموبايل ========== -->
    <div class="mobile-overlay" id="mobileOverlay"></div>
    <nav class="mobile-nav" id="mobileNav">
        
        <!-- معلومات المستخدم -->
        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="mobile-user-info">
                <img 
                    src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['user_name']); ?>&background=ffffff&color=ff3e3e&bold=true&size=100" 
                    alt="الصورة"
                >
                <div class="info">
                    <h4><?php echo htmlspecialchars($_SESSION['user_name']); ?></h4>
                    <p>مرحباً بعودتك!</p>
                </div>
            </div>
        <?php else: ?>
            <div class="mobile-user-info">
                <img src="https://ui-avatars.com/api/?name=زائر&background=ffffff&color=ff3e3e&bold=true&size=100" alt="زائر">
                <div class="info">
                    <h4>مرحباً بك!</h4>
                    <p>سجل دخولك للمزيد من المميزات</p>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- البحث -->
        <div class="mobile-search">
            <form action="index.php" method="GET" class="search-box">
                <input type="text" name="search" placeholder="ابحث هنا...">
                <button type="submit" class="search-btn"><i class="fas fa-search"></i></button>
            </form>
        </div>
        
        <!-- الروابط -->
        <div class="mobile-links">
            <a href="index.php" class="mobile-link">
                <i class="fas fa-home"></i>
                <span>الرئيسية</span>
            </a>
            
            <a href="cart.php" class="mobile-link">
                <i class="fas fa-shopping-cart"></i>
                <span>سلة المشتريات</span>
                <span class="link-badge" id="mobile-cart-count">0</span>
            </a>
            
            <a href="wishlist.php" class="mobile-link">
                <i class="fas fa-heart"></i>
                <span>المفضلة</span>
                <span class="link-badge" id="mobile-wishlist-count">0</span>
            </a>
            
            <a href="track.php" class="mobile-link">
                <i class="fas fa-truck"></i>
                <span>تتبع طلباتك</span>
            </a>
            
            <a href="contact.php" class="mobile-link">
                <i class="fas fa-headset"></i>
                <span>تواصل معنا</span>
            </a>
            
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="my_orders.php" class="mobile-link">
                    <i class="fas fa-box"></i>
                    <span>طلباتي</span>
                </a>
                <a href="logout.php" class="mobile-link" style="color:#dc3545;">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>تسجيل الخروج</span>
                </a>
            <?php else: ?>
                <a href="login.php" class="mobile-link" style="background:var(--primary); border-color:var(--primary);">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>تسجيل الدخول</span>
                </a>
                <a href="register.php" class="mobile-link">
                    <i class="fas fa-user-plus"></i>
                    <span>إنشاء حساب جديد</span>
                </a>
            <?php endif; ?>
        </div>
    </nav>
    
    <!-- رسائل التنبيه -->
    <div id="toast"></div>
    
    <!-- سكريبت الهيدر -->
    <script>
        // تأثير الهيدر عند التمرير
        window.addEventListener('scroll', function() {
            const header = document.getElementById('mainHeader');
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });
        
        // قائمة الموبايل
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const mobileNav = document.getElementById('mobileNav');
        const mobileOverlay = document.getElementById('mobileOverlay');
        
        function toggleMobileMenu() {
            mobileMenuBtn.classList.toggle('active');
            mobileNav.classList.toggle('active');
            mobileOverlay.classList.toggle('active');
            document.body.style.overflow = mobileNav.classList.contains('active') ? 'hidden' : '';
        }
        
        mobileMenuBtn.addEventListener('click', toggleMobileMenu);
        mobileOverlay.addEventListener('click', toggleMobileMenu);
        
        // تحديث موقع القائمة الجانبية بناءً على ارتفاع الهيدر
        function updateMobileNavPosition() {
            const header = document.getElementById('mainHeader');
            if (header && mobileNav && mobileOverlay) {
                const headerHeight = header.offsetHeight;
                mobileNav.style.top = headerHeight + 'px';
                mobileNav.style.height = 'calc(100vh - ' + headerHeight + 'px)';
                mobileOverlay.style.top = headerHeight + 'px';
                mobileOverlay.style.height = 'calc(100vh - ' + headerHeight + 'px)';
            }
        }
        
        // تحديث عند تحميل الصفحة وتغيير حجم النافذة
        window.addEventListener('load', updateMobileNavPosition);
        window.addEventListener('resize', updateMobileNavPosition);
        
        // تحديث بعد تحميل DOM
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(updateMobileNavPosition, 100);
        });
        
        // تحديث عدادات الموبايل
        function updateMobileCounters() {
            const cartCount = document.getElementById('cart-count');
            const wishlistCount = document.getElementById('wishlist-count');
            const mobileCartCount = document.getElementById('mobile-cart-count');
            const mobileWishlistCount = document.getElementById('mobile-wishlist-count');
            const cartCountDesktop = document.querySelector('.cart-count-desktop');
            
            if (cartCount && mobileCartCount) {
                mobileCartCount.textContent = cartCount.textContent;
            }
            if (cartCount && cartCountDesktop) {
                cartCountDesktop.textContent = cartCount.textContent;
            }
            if (wishlistCount && mobileWishlistCount) {
                mobileWishlistCount.textContent = wishlistCount.textContent;
            }
        }
        
        // مراقبة التغييرات
        const observer = new MutationObserver(updateMobileCounters);
        const cartBadge = document.getElementById('cart-count');
        const wishlistBadge = document.getElementById('wishlist-count');
        
        if (cartBadge) observer.observe(cartBadge, { childList: true });
        if (wishlistBadge) observer.observe(wishlistBadge, { childList: true });
        
        // تحديث أولي
        setTimeout(updateMobileCounters, 500);
    </script>
    
    <!-- سكريبت الإشعارات -->
    <script src="public/js/notifications.js"></script>
    
    <!-- أنماط الإشعارات -->
    <style>
        .notification-wrapper { position: relative; }
        .notification-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            width: 350px;
            background: #1a1a1a;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.4);
            border: 1px solid #333;
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: all 0.3s ease;
            z-index: 9999;
            max-height: 450px;
            overflow: hidden;
        }
        .notification-dropdown.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #333;
            background: #222;
        }
        .notification-header h4 { margin: 0; font-size: 16px; color: #fff; }
        .mark-all-btn {
            background: none;
            border: none;
            color: var(--primary, #ff3e3e);
            cursor: pointer;
            font-size: 12px;
        }
        .notification-list {
            max-height: 350px;
            overflow-y: auto;
        }
        .notification-item {
            display: flex;
            gap: 12px;
            padding: 12px 15px;
            border-bottom: 1px solid #333;
            cursor: pointer;
            transition: background 0.2s;
        }
        .notification-item:hover { background: #252525; }
        .notification-item.unread { background: rgba(255,62,62,0.1); }
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary, #ff3e3e);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .notification-content { flex: 1; min-width: 0; }
        .notification-content h4 {
            margin: 0 0 4px;
            font-size: 14px;
            color: #fff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .notification-content p {
            margin: 0;
            font-size: 12px;
            color: #999;
            line-height: 1.4;
        }
        .notification-time {
            font-size: 11px;
            color: #999;
            margin-top: 4px;
            display: block;
        }
        .notification-image {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            object-fit: cover;
        }
        .notification-empty, .notification-loading {
            padding: 40px 20px;
            text-align: center;
            color: #888;
        }
        .notification-empty i { font-size: 40px; margin-bottom: 10px; display: block; color: #555; }
        
        /* إصلاح قائمة الإشعارات على الموبايل */
        @media (max-width: 768px) {
            .notification-dropdown { 
                position: fixed;
                top: 60px;
                left: 10px;
                right: 10px;
                width: auto;
                max-width: none;
                max-height: calc(100vh - 80px);
                border-radius: 15px;
                box-shadow: 0 10px 50px rgba(0,0,0,0.3);
            }
            .notification-list {
                max-height: calc(100vh - 180px);
            }
        }
        @media (max-width: 480px) {
            .notification-dropdown { 
                top: 55px;
                left: 5px;
                right: 5px;
            }
            .notification-header { padding: 12px; }
            .notification-header h4 { font-size: 14px; }
            .mark-all-btn { font-size: 11px; }
            .notification-item { padding: 10px 12px; }
            .notification-content h4 { font-size: 13px; }
            .notification-content p { font-size: 11px; }
            .notification-list {
                max-height: calc(100vh - 160px);
            }
        }
    </style>

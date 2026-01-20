<?php
// الحصول على الإعدادات
$footer_store_name = function_exists('getSetting') ? getSetting('store_name', 'مشترياتي') : 'مشترياتي';
$footer_store_desc = function_exists('getSetting') ? getSetting('store_description', 'متجرك الإلكتروني الأول للتسوق بأمان وراحة') : 'متجرك الإلكتروني الأول للتسوق بأمان وراحة';
$footer_store_logo = function_exists('getSetting') ? getSetting('store_logo', '') : '';

// معلومات التواصل
$footer_phone = function_exists('getSetting') ? getSetting('contact_phone', '+966 XX XXX XXXX') : '+966 XX XXX XXXX';
$footer_email = function_exists('getSetting') ? getSetting('contact_email', 'info@myshop.com') : 'info@myshop.com';
$footer_address = function_exists('getSetting') ? getSetting('contact_address', 'المملكة العربية السعودية') : 'المملكة العربية السعودية';
$footer_whatsapp = function_exists('getSetting') ? getSetting('contact_whatsapp', '') : '';

// وسائل التواصل الاجتماعي
$social_facebook = function_exists('getSetting') ? getSetting('social_facebook', '') : '';
$social_twitter = function_exists('getSetting') ? getSetting('social_twitter', '') : '';
$social_instagram = function_exists('getSetting') ? getSetting('social_instagram', '') : '';
$social_snapchat = function_exists('getSetting') ? getSetting('social_snapchat', '') : '';
$social_tiktok = function_exists('getSetting') ? getSetting('social_tiktok', '') : '';
$social_youtube = function_exists('getSetting') ? getSetting('social_youtube', '') : '';
?>
<!-- ========================================
     الفوتر الرئيسي
     ======================================== -->
<footer class="main-footer">
    <div class="footer-container">
        
        <!-- معلومات المتجر -->
        <div class="footer-section">
            <div class="footer-logo">
                <?php if ($footer_store_logo): ?>
                    <img src="<?php echo htmlspecialchars($footer_store_logo); ?>" alt="<?php echo htmlspecialchars($footer_store_name); ?>" style="height: 35px;">
                <?php else: ?>
                    <i class="fas fa-shopping-bag"></i>
                <?php endif; ?>
                <span><?php echo htmlspecialchars($footer_store_name); ?><span class="dot">.</span></span>
            </div>
            <p class="footer-desc">
                <?php echo htmlspecialchars($footer_store_desc); ?>
            </p>
            <div class="social-links">
                <?php if ($social_facebook): ?>
                    <a href="<?php echo htmlspecialchars($social_facebook); ?>" target="_blank" title="فيسبوك"><i class="fab fa-facebook-f"></i></a>
                <?php endif; ?>
                <?php if ($social_twitter): ?>
                    <a href="<?php echo htmlspecialchars($social_twitter); ?>" target="_blank" title="تويتر"><i class="fab fa-twitter"></i></a>
                <?php endif; ?>
                <?php if ($social_instagram): ?>
                    <a href="<?php echo htmlspecialchars($social_instagram); ?>" target="_blank" title="انستغرام"><i class="fab fa-instagram"></i></a>
                <?php endif; ?>
                <?php if ($footer_whatsapp): ?>
                    <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $footer_whatsapp); ?>" target="_blank" title="واتساب"><i class="fab fa-whatsapp"></i></a>
                <?php endif; ?>
                <?php if ($social_snapchat): ?>
                    <a href="<?php echo htmlspecialchars($social_snapchat); ?>" target="_blank" title="سناب شات"><i class="fab fa-snapchat"></i></a>
                <?php endif; ?>
                <?php if ($social_tiktok): ?>
                    <a href="<?php echo htmlspecialchars($social_tiktok); ?>" target="_blank" title="تيك توك"><i class="fab fa-tiktok"></i></a>
                <?php endif; ?>
                <?php if ($social_youtube): ?>
                    <a href="<?php echo htmlspecialchars($social_youtube); ?>" target="_blank" title="يوتيوب"><i class="fab fa-youtube"></i></a>
                <?php endif; ?>
                <?php if (!$social_facebook && !$social_twitter && !$social_instagram && !$footer_whatsapp): ?>
                    <a href="#" title="فيسبوك"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" title="تويتر"><i class="fab fa-twitter"></i></a>
                    <a href="#" title="انستغرام"><i class="fab fa-instagram"></i></a>
                    <a href="#" title="واتساب"><i class="fab fa-whatsapp"></i></a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- روابط سريعة -->
        <div class="footer-section">
            <h4>روابط سريعة</h4>
            <ul class="footer-links">
                <li><a href="index.php"><i class="fas fa-home"></i> الرئيسية</a></li>
                <li><a href="cart.php"><i class="fas fa-shopping-cart"></i> سلة المشتريات</a></li>
                <li><a href="wishlist.php"><i class="fas fa-heart"></i> المفضلة</a></li>
                <li><a href="track.php"><i class="fas fa-truck"></i> تتبع طلبك</a></li>
                <li><a href="contact.php"><i class="fas fa-envelope"></i> تواصل معنا</a></li>
            </ul>
        </div>
        
        <!-- الفئات -->
        <div class="footer-section">
            <h4>الفئات</h4>
            <ul class="footer-links">
                <li><a href="index.php?cat=electronics">إلكترونيات</a></li>
                <li><a href="index.php?cat=fashion">أزياء رجالية</a></li>
                <li><a href="index.php?cat=women">أزياء نسائية</a></li>
                <li><a href="index.php?cat=watches">ساعات</a></li>
                <li><a href="index.php?cat=perfume">عطور</a></li>
            </ul>
        </div>
        
        <!-- معلومات التواصل -->
        <div class="footer-section">
            <h4>تواصل معنا</h4>
            <ul class="contact-info">
                <li>
                    <i class="fas fa-phone"></i>
                    <span><?php echo htmlspecialchars($footer_phone); ?></span>
                </li>
                <li>
                    <i class="fas fa-envelope"></i>
                    <span><?php echo htmlspecialchars($footer_email); ?></span>
                </li>
                <li>
                    <i class="fas fa-map-marker-alt"></i>
                    <span><?php echo htmlspecialchars($footer_address); ?></span>
                </li>
                <li>
                    <i class="fas fa-clock"></i>
                    <span>24/7 خدمة العملاء</span>
                </li>
            </ul>
        </div>
        
    </div>
    
    <!-- شريط الحقوق -->
    <div class="footer-bottom">
        <div class="footer-bottom-content">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($footer_store_name); ?>. جميع الحقوق محفوظة.</p>
            <div class="payment-methods">
                <span>طرق الدفع:</span>
                <i class="fas fa-money-bill-wave" title="الدفع عند الاستلام"></i>
                <i class="fab fa-cc-visa" title="فيزا"></i>
                <i class="fab fa-cc-mastercard" title="ماستركارد"></i>
            </div>
        </div>
    </div>
</footer>

<style>
/* ========================================
   تنسيقات الفوتر
   ======================================== */
.main-footer {
    background: #0d0d0d;
    border-top: 1px solid #222;
    margin-top: 50px;
    padding-top: 50px;
}

.footer-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 40px;
}

.footer-section h4 {
    color: white;
    font-size: 18px;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #ff3e3e;
    display: inline-block;
}

.footer-logo {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
}

.footer-logo i {
    font-size: 30px;
    color: #ff3e3e;
}

.footer-logo span {
    font-size: 24px;
    font-weight: 900;
    color: white;
}

.footer-logo .dot {
    color: #ff3e3e;
}

.footer-desc {
    color: #888;
    font-size: 14px;
    line-height: 1.8;
    margin-bottom: 20px;
}

.social-links {
    display: flex;
    gap: 10px;
}

.social-links a {
    width: 40px;
    height: 40px;
    background: #1a1a1a;
    border: 1px solid #333;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #888;
    transition: 0.3s;
}

.social-links a:hover {
    background: #ff3e3e;
    border-color: #ff3e3e;
    color: white;
    transform: translateY(-3px);
}

.footer-links {
    list-style: none;
    padding: 0;
    margin: 0;
}

.footer-links li {
    margin-bottom: 12px;
}

.footer-links a {
    color: #888;
    font-size: 14px;
    transition: 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.footer-links a:hover {
    color: #ff3e3e;
    padding-right: 5px;
}

.footer-links a i {
    font-size: 12px;
    width: 15px;
}

.contact-info {
    list-style: none;
    padding: 0;
    margin: 0;
}

.contact-info li {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 15px;
    color: #888;
    font-size: 14px;
}

.contact-info li i {
    color: #ff3e3e;
    width: 20px;
}

.footer-bottom {
    background: #080808;
    margin-top: 40px;
    padding: 20px;
}

.footer-bottom-content {
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.footer-bottom p {
    color: #666;
    font-size: 14px;
    margin: 0;
}

.payment-methods {
    display: flex;
    align-items: center;
    gap: 15px;
    color: #666;
    font-size: 14px;
}

.payment-methods i {
    font-size: 24px;
    color: #888;
}

@media (max-width: 768px) {
    .footer-container {
        grid-template-columns: 1fr 1fr;
    }
    .footer-bottom-content {
        flex-direction: column;
        text-align: center;
    }
}

@media (max-width: 480px) {
    .footer-container {
        grid-template-columns: 1fr;
        text-align: center;
    }
    .footer-logo, .social-links, .footer-links a {
        justify-content: center;
    }
    .contact-info li {
        justify-content: center;
    }
}
</style>

<script src="public/js/script.js?v=11"></script>
</body>
</html>


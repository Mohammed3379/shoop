<?php
/**
 * Header مشترك للوحة الإدارة
 */
if (!isset($page_title)) $page_title = 'لوحة التحكم';
if (!isset($page_icon)) $page_icon = 'fa-home';

// جلب عدد الطلبات الجديدة
$new_orders_count = 0;
try {
    $result = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'");
    if ($result) $new_orders_count = $result->fetch_assoc()['count'];
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - لوحة الإدارة</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Admin Styles -->
    <?php 
    // تحديد المسار الأساسي للأدمن
    $admin_base = '';
    if (basename(dirname($_SERVER['PHP_SELF'])) === 'includes') {
        $admin_base = '../';
    }
    ?>
    <link rel="stylesheet" href="<?php echo $admin_base; ?>css/admin-style.css">
    
    <style>
        body { font-family: 'Cairo', 'Segoe UI', sans-serif; }
    </style>
</head>
<body class="admin-body">
    
    <!-- Sidebar Overlay (للموبايل) -->
    <div class="sidebar-overlay"></div>
    
    <div class="admin-wrapper">
        
        <!-- ========== الشريط الجانبي ========== -->
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="fas fa-store"></i>
                </div>
                <div class="sidebar-brand">
                    <h1>مشترياتي</h1>
                    <span>لوحة الإدارة</span>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <!-- القسم الرئيسي -->
                <div class="nav-section">
                    <div class="nav-section-title">الرئيسية</div>
                    
                    <?php if (hasPermission('dashboard.view')): ?>
                    <a href="admin.php" class="nav-item">
                        <i class="fas fa-chart-pie"></i>
                        <span>لوحة القيادة</span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (hasPermission('orders.view')): ?>
                    <a href="admin_orders.php" class="nav-item">
                        <i class="fas fa-shopping-bag"></i>
                        <span>الطلبات</span>
                        <?php if ($new_orders_count > 0): ?>
                            <span class="nav-badge"><?php echo $new_orders_count; ?></span>
                        <?php endif; ?>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (hasPermission('notifications.view')): ?>
                    <a href="admin_notifications.php" class="nav-item">
                        <i class="fas fa-bell"></i>
                        <span>الإشعارات</span>
                    </a>
                    <?php endif; ?>
                </div>
                
                <!-- إدارة المتجر -->
                <?php if (hasAnyPermission(['products.view', 'categories.view', 'users.view', 'agents.view'])): ?>
                <div class="nav-section">
                    <div class="nav-section-title">إدارة المتجر</div>
                    
                    <?php if (hasPermission('products.view')): ?>
                    <a href="admin_products.php" class="nav-item">
                        <i class="fas fa-boxes"></i>
                        <span>جميع المنتجات</span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (hasPermission('products.create')): ?>
                    <a href="add_product.php" class="nav-item">
                        <i class="fas fa-plus-circle"></i>
                        <span>إضافة منتج</span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (hasPermission('categories.view')): ?>
                    <a href="admin_categories.php" class="nav-item">
                        <i class="fas fa-tags"></i>
                        <span>الفئات</span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (hasPermission('users.view')): ?>
                    <a href="admin_users.php" class="nav-item">
                        <i class="fas fa-users"></i>
                        <span>العملاء</span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (hasPermission('agents.view')): ?>
                    <a href="admin_agents.php" class="nav-item">
                        <i class="fas fa-motorcycle"></i>
                        <span>فريق التوصيل</span>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- الإعدادات -->
                <?php if (hasAnyPermission(['banners.view', 'settings.view'])): ?>
                <div class="nav-section">
                    <div class="nav-section-title">الإعدادات</div>
                    
                    <?php if (hasPermission('banners.view')): ?>
                    <a href="admin_banners.php" class="nav-item">
                        <i class="fas fa-images"></i>
                        <span>البانرات الإعلانية</span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (hasPermission('settings.view')): ?>
                    <a href="admin_payments.php" class="nav-item">
                        <i class="fas fa-credit-card"></i>
                        <span>طرق الدفع</span>
                    </a>
                    
                    <a href="admin_shipping.php" class="nav-item">
                        <i class="fas fa-truck"></i>
                        <span>طرق الشحن</span>
                    </a>
                    
                    <a href="admin_settings.php" class="nav-item">
                        <i class="fas fa-cog"></i>
                        <span>إعدادات المتجر</span>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- إدارة النظام -->
                <?php if (hasAnyPermission(['admins.view', 'admins.roles', 'logs.view'])): ?>
                <div class="nav-section">
                    <div class="nav-section-title">إدارة النظام</div>
                    
                    <?php if (hasPermission('admins.view')): ?>
                    <a href="admin_admins.php" class="nav-item">
                        <i class="fas fa-user-shield"></i>
                        <span>المسؤولين</span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (hasPermission('admins.roles')): ?>
                    <a href="admin_roles.php" class="nav-item">
                        <i class="fas fa-user-tag"></i>
                        <span>الأدوار والصلاحيات</span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (hasPermission('logs.view')): ?>
                    <a href="admin_logs.php" class="nav-item">
                        <i class="fas fa-history"></i>
                        <span>سجل النشاطات</span>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- روابط سريعة -->
                <div class="nav-section">
                    <div class="nav-section-title">روابط سريعة</div>
                    
                    <a href="../index.php" target="_blank" class="nav-item">
                        <i class="fas fa-external-link-alt"></i>
                        <span>زيارة المتجر</span>
                    </a>
                    
                    <a href="admin_logout.php" class="nav-item" style="color: #ef4444;">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>تسجيل الخروج</span>
                    </a>
                </div>
            </nav>
            
            <div class="sidebar-footer">
                <div class="admin-profile">
                    <div class="admin-avatar">
                        <?php echo mb_substr($_SESSION['admin_name'] ?? 'م', 0, 1); ?>
                    </div>
                    <div class="admin-info">
                        <h4><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'مدير'); ?></h4>
                        <span><?php 
                            $role = $_SESSION['admin_role'] ?? 'مسؤول';
                            echo is_array($role) ? ($role['name_ar'] ?? 'مسؤول') : $role;
                        ?></span>
                    </div>
                </div>
            </div>
        </aside>
        
        <!-- ========== المحتوى الرئيسي ========== -->
        <main class="admin-main">
            
            <!-- الهيدر العلوي -->
            <header class="admin-header">
                <div class="header-right">
                    <button class="menu-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 class="page-title">
                        <i class="fas <?php echo $page_icon; ?>"></i>
                        <?php echo $page_title; ?>
                    </h1>
                </div>
                
                <div class="header-left">
                    <button class="header-btn" data-tooltip="الإشعارات">
                        <i class="fas fa-bell"></i>
                        <?php if ($new_orders_count > 0): ?>
                            <span class="notification-dot"></span>
                        <?php endif; ?>
                    </button>
                    
                    <a href="../index.php" target="_blank" class="header-btn" data-tooltip="زيارة المتجر">
                        <i class="fas fa-store"></i>
                    </a>
                </div>
            </header>
            
            <!-- محتوى الصفحة -->
            <div class="admin-content">

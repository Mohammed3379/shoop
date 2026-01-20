<?php
/**
 * صفحة إدارة الإشعارات
 * Admin Notifications Management
 */

include '../app/config/database.php';
include 'admin_auth.php';
include '../app/services/NotificationService.php';

checkAdminAuth();

// التحقق من الصلاحيات
if (!hasPermission('notifications.view')) {
    include 'includes/access_denied.php';
    exit;
}

$page_title = 'إدارة الإشعارات';
$page_icon = 'fa-bell';

$notificationService = new NotificationService($conn);
$csrf_token = getAdminCSRF();

// معالجة الحذف
if (isset($_GET['delete']) && isset($_GET['token'])) {
    if (!verifyAdminCSRF($_GET['token'])) {
        die("طلب غير صالح!");
    }
    if (hasPermission('notifications.delete')) {
        $id = intval($_GET['delete']);
        $result = $notificationService->delete($id);
        if ($result['success']) {
            logAdminAction('delete_notification', "تم حذف الإشعار رقم: $id");
            header("Location: admin_notifications.php?deleted=1");
            exit();
        }
    }
}

// معالجة الإرسال
if (isset($_GET['send']) && isset($_GET['token'])) {
    if (!verifyAdminCSRF($_GET['token'])) {
        die("طلب غير صالح!");
    }
    if (hasPermission('notifications.send')) {
        $id = intval($_GET['send']);
        $result = $notificationService->send($id);
        if ($result['success']) {
            logAdminAction('send_notification', "تم إرسال الإشعار رقم: $id");
            header("Location: admin_notifications.php?sent=1&count=" . ($result['recipients_count'] ?? 0));
            exit();
        } else {
            $error_message = $result['message'];
        }
    }
}

// الفلترة
$filters = [];
if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
if (!empty($_GET['type'])) $filters['type'] = $_GET['type'];
if (!empty($_GET['search'])) $filters['search'] = $_GET['search'];

$notifications = $notificationService->getAll($filters);

include 'includes/admin_header.php';
?>

<!-- رسائل النجاح -->
<?php if (isset($_GET['deleted'])): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> تم حذف الإشعار بنجاح!</div>
<?php endif; ?>

<?php if (isset($_GET['sent'])): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> تم إرسال الإشعار بنجاح إلى <?php echo intval($_GET['count']); ?> مستخدم!</div>
<?php endif; ?>

<?php if (isset($_GET['saved'])): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> تم حفظ الإشعار بنجاح!</div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<!-- شريط الأدوات -->
<div class="card notifications-toolbar" style="margin-bottom: 20px;">
    <div class="card-body" style="padding: 15px;">
        <div class="toolbar-wrapper">
            <!-- الفلاتر -->
            <form method="GET" class="filters-form">
                <select name="status" class="form-control" onchange="this.form.submit()">
                    <option value="">كل الحالات</option>
                    <option value="draft" <?php echo ($_GET['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>مسودة</option>
                    <option value="scheduled" <?php echo ($_GET['status'] ?? '') === 'scheduled' ? 'selected' : ''; ?>>مجدول</option>
                    <option value="sent" <?php echo ($_GET['status'] ?? '') === 'sent' ? 'selected' : ''; ?>>مرسل</option>
                </select>
                <select name="type" class="form-control" onchange="this.form.submit()">
                    <option value="">كل الأنواع</option>
                    <option value="general" <?php echo ($_GET['type'] ?? '') === 'general' ? 'selected' : ''; ?>>عام</option>
                    <option value="custom" <?php echo ($_GET['type'] ?? '') === 'custom' ? 'selected' : ''; ?>>مخصص</option>
                    <option value="individual" <?php echo ($_GET['type'] ?? '') === 'individual' ? 'selected' : ''; ?>>فردي</option>
                </select>
                <input type="text" name="search" class="form-control search-input" placeholder="بحث..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                <button type="submit" class="btn btn-outline"><i class="fas fa-search"></i></button>
            </form>
            
            <!-- زر إضافة -->
            <?php if (hasPermission('notifications.create')): ?>
            <a href="admin_notification_create.php" class="btn btn-primary add-btn">
                <i class="fas fa-plus"></i> <span>إنشاء إشعار جديد</span>
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- جدول الإشعارات -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-bell"></i> قائمة الإشعارات</h3>
        <span class="badge badge-info"><?php echo count($notifications); ?> إشعار</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>العنوان</th>
                        <th>النوع</th>
                        <th>التصنيف</th>
                        <th>الحالة</th>
                        <th>التاريخ</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($notifications)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">
                            <i class="fas fa-bell-slash" style="font-size: 40px; margin-bottom: 15px; display: block;"></i>
                            لا توجد إشعارات
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($notifications as $notif): 
                        $type_labels = ['general' => 'عام', 'custom' => 'مخصص', 'individual' => 'فردي'];
                        $category_labels = ['offer' => 'عرض', 'new_product' => 'منتج جديد', 'cart_reminder' => 'تذكير سلة', 'promotional' => 'ترويجي'];
                        $status_badges = [
                            'draft' => ['badge-warning', 'مسودة'],
                            'scheduled' => ['badge-info', 'مجدول'],
                            'sent' => ['badge-success', 'مرسل']
                        ];
                        $badge = $status_badges[$notif['status']] ?? ['badge-info', $notif['status']];
                    ?>
                    <tr>
                        <td><?php echo $notif['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars(mb_substr($notif['title'], 0, 40)); ?></strong>
                            <?php if ($notif['image']): ?>
                                <i class="fas fa-image" style="color: #888; margin-right: 5px;" title="يحتوي صورة"></i>
                            <?php endif; ?>
                            <?php if ($notif['link']): ?>
                                <i class="fas fa-link" style="color: #888; margin-right: 5px;" title="يحتوي رابط"></i>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $type_labels[$notif['type']] ?? $notif['type']; ?></td>
                        <td><?php echo $category_labels[$notif['category']] ?? $notif['category']; ?></td>
                        <td><span class="badge <?php echo $badge[0]; ?>"><?php echo $badge[1]; ?></span></td>
                        <td style="color: var(--text-muted); font-size: 13px;">
                            <?php echo date('Y/m/d H:i', strtotime($notif['created_at'])); ?>
                            <?php if ($notif['scheduled_at']): ?>
                                <br><small>مجدول: <?php echo date('Y/m/d H:i', strtotime($notif['scheduled_at'])); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-btns">
                                <?php if ($notif['status'] !== 'sent' && hasPermission('notifications.send')): ?>
                                <a href="admin_notifications.php?send=<?php echo $notif['id']; ?>&token=<?php echo $csrf_token; ?>" 
                                   class="action-btn" style="background: #10b981;" data-confirm="هل تريد إرسال هذا الإشعار؟" title="إرسال">
                                    <i class="fas fa-paper-plane"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($notif['status'] !== 'sent' && hasPermission('notifications.edit')): ?>
                                <a href="admin_notification_create.php?id=<?php echo $notif['id']; ?>" class="action-btn edit" title="تعديل">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($notif['status'] === 'sent'): ?>
                                <a href="admin_notification_stats.php?id=<?php echo $notif['id']; ?>" class="action-btn" style="background: #6366f1;" title="إحصائيات">
                                    <i class="fas fa-chart-bar"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php if (hasPermission('notifications.delete')): ?>
                                <a href="admin_notifications.php?delete=<?php echo $notif['id']; ?>&token=<?php echo $csrf_token; ?>" 
                                   class="action-btn delete" data-confirm="هل تريد حذف هذا الإشعار؟" title="حذف">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Mobile Cards View -->
        <div class="notification-cards">
            <?php if (empty($notifications)): ?>
            <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                <i class="fas fa-bell-slash" style="font-size: 40px; margin-bottom: 15px; display: block;"></i>
                لا توجد إشعارات
            </div>
            <?php else: ?>
            <?php foreach ($notifications as $notif): 
                $type_labels = ['general' => 'عام', 'custom' => 'مخصص', 'individual' => 'فردي'];
                $category_labels = ['offer' => 'عرض', 'new_product' => 'منتج جديد', 'cart_reminder' => 'تذكير سلة', 'promotional' => 'ترويجي'];
                $status_badges = [
                    'draft' => ['badge-warning', 'مسودة'],
                    'scheduled' => ['badge-info', 'مجدول'],
                    'sent' => ['badge-success', 'مرسل']
                ];
                $badge = $status_badges[$notif['status']] ?? ['badge-info', $notif['status']];
            ?>
            <div class="notification-card">
                <div class="notification-card-header">
                    <span class="notification-card-title">
                        <?php echo htmlspecialchars($notif['title']); ?>
                        <?php if ($notif['image']): ?><i class="fas fa-image" style="color: #888; margin-right: 5px;"></i><?php endif; ?>
                        <?php if ($notif['link']): ?><i class="fas fa-link" style="color: #888; margin-right: 5px;"></i><?php endif; ?>
                    </span>
                    <span class="badge <?php echo $badge[0]; ?>"><?php echo $badge[1]; ?></span>
                </div>
                <div class="notification-card-meta">
                    <span><i class="fas fa-hashtag"></i> <?php echo $notif['id']; ?></span>
                    <span><i class="fas fa-tag"></i> <?php echo $type_labels[$notif['type']] ?? $notif['type']; ?></span>
                    <span><i class="fas fa-folder"></i> <?php echo $category_labels[$notif['category']] ?? $notif['category']; ?></span>
                    <span><i class="fas fa-calendar"></i> <?php echo date('Y/m/d', strtotime($notif['created_at'])); ?></span>
                </div>
                <div class="notification-card-actions">
                    <?php if ($notif['status'] !== 'sent' && hasPermission('notifications.send')): ?>
                    <a href="admin_notifications.php?send=<?php echo $notif['id']; ?>&token=<?php echo $csrf_token; ?>" 
                       class="action-btn" style="background: #10b981;" data-confirm="هل تريد إرسال هذا الإشعار؟">
                        <i class="fas fa-paper-plane"></i>
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($notif['status'] !== 'sent' && hasPermission('notifications.edit')): ?>
                    <a href="admin_notification_create.php?id=<?php echo $notif['id']; ?>" class="action-btn edit">
                        <i class="fas fa-edit"></i>
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($notif['status'] === 'sent'): ?>
                    <a href="admin_notification_stats.php?id=<?php echo $notif['id']; ?>" class="action-btn" style="background: #6366f1;">
                        <i class="fas fa-chart-bar"></i>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (hasPermission('notifications.delete')): ?>
                    <a href="admin_notifications.php?delete=<?php echo $notif['id']; ?>&token=<?php echo $csrf_token; ?>" 
                       class="action-btn delete" data-confirm="هل تريد حذف هذا الإشعار؟">
                        <i class="fas fa-trash"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Notifications Page Styles */
.toolbar-wrapper {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.filters-form {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    flex: 1;
}

.filters-form .form-control {
    width: auto;
    min-width: 120px;
}

.filters-form .search-input {
    width: 200px;
}

.add-btn {
    white-space: nowrap;
}

/* Mobile Cards View */
.notification-cards {
    display: none;
}

.notification-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 15px;
    margin-bottom: 12px;
}

.notification-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
}

.notification-card-title {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 14px;
    flex: 1;
    margin-left: 10px;
}

.notification-card-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 12px;
    font-size: 12px;
    color: var(--text-muted);
}

.notification-card-meta span {
    display: flex;
    align-items: center;
    gap: 5px;
}

.notification-card-actions {
    display: flex;
    gap: 8px;
    padding-top: 12px;
    border-top: 1px solid var(--border-color);
}

.notification-card-actions .action-btn {
    flex: 1;
    justify-content: center;
}

/* Responsive Styles */
@media (max-width: 992px) {
    .admin-table td:nth-child(4),
    .admin-table th:nth-child(4) {
        display: none;
    }
}

@media (max-width: 768px) {
    .toolbar-wrapper {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filters-form {
        flex-direction: column;
        width: 100%;
    }
    
    .filters-form .form-control,
    .filters-form .search-input {
        width: 100% !important;
        min-width: 100%;
    }
    
    .filters-form .btn {
        width: 100%;
    }
    
    .add-btn {
        width: 100%;
        justify-content: center;
        text-align: center;
    }
    
    /* Hide Table, Show Cards */
    .table-container {
        display: none;
    }
    
    .notification-cards {
        display: block;
        padding: 15px;
    }
    
    .card-header {
        flex-direction: column;
        gap: 10px;
        align-items: flex-start !important;
    }
}

@media (max-width: 576px) {
    .notifications-toolbar .card-body {
        padding: 10px !important;
    }
    
    .filters-form {
        gap: 8px;
    }
    
    .notification-card {
        padding: 12px;
    }
    
    .notification-card-title {
        font-size: 13px;
    }
    
    .notification-card-meta {
        font-size: 11px;
    }
    
    .action-btn {
        width: 36px;
        height: 36px;
        font-size: 14px;
    }
    
    .badge {
        font-size: 10px;
        padding: 4px 8px;
    }
}
</style>

<?php include 'includes/admin_footer.php'; ?>

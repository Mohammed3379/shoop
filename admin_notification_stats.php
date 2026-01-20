<?php
/**
 * صفحة إحصائيات الإشعارات
 * Notification Statistics Page
 */

include '../app/config/database.php';
include 'admin_auth.php';
include '../app/services/NotificationService.php';

checkAdminAuth();

if (!hasPermission('notifications.stats')) {
    include 'includes/access_denied.php';
    exit;
}

$page_title = 'إحصائيات الإشعارات';
$page_icon = 'fa-chart-bar';

$notificationService = new NotificationService($conn);

// إحصائيات إشعار محدد
$notificationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$notification = null;
$stats = null;

if ($notificationId > 0) {
    $notification = $notificationService->getById($notificationId);
    if ($notification && $notification['status'] === 'sent') {
        $stats = $notificationService->getStats($notificationId);
    }
}

// إحصائيات عامة
$totalSent = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE status = 'sent'")->fetch_assoc()['count'];
$totalDraft = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE status = 'draft'")->fetch_assoc()['count'];
$totalScheduled = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE status = 'scheduled'")->fetch_assoc()['count'];

// متوسط نسبة الفتح
$avgOpenRate = $conn->query("
    SELECT AVG(open_rate) as avg_rate FROM (
        SELECT 
            n.id,
            COUNT(un.id) as total,
            SUM(CASE WHEN un.is_read = 1 THEN 1 ELSE 0 END) as read_count,
            (SUM(CASE WHEN un.is_read = 1 THEN 1 ELSE 0 END) / COUNT(un.id) * 100) as open_rate
        FROM notifications n
        JOIN user_notifications un ON n.id = un.notification_id
        WHERE n.status = 'sent'
        GROUP BY n.id
    ) as rates
")->fetch_assoc()['avg_rate'] ?? 0;

// آخر الإشعارات المرسلة مع إحصائياتها
$recentNotifications = $conn->query("
    SELECT 
        n.*,
        COUNT(un.id) as recipients,
        SUM(CASE WHEN un.is_read = 1 THEN 1 ELSE 0 END) as `reads`
    FROM notifications n
    LEFT JOIN user_notifications un ON n.id = un.notification_id
    WHERE n.status = 'sent'
    GROUP BY n.id
    ORDER BY n.sent_at DESC
    LIMIT 10
");

include 'includes/admin_header.php';
?>

<?php if ($notification && $stats): ?>
<!-- إحصائيات إشعار محدد -->
<div class="card notification-detail-card" style="margin-bottom: 20px;">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-bell"></i> <?php echo htmlspecialchars($notification['title']); ?></h3>
        <a href="admin_notification_stats.php" class="btn btn-sm btn-outline">عرض الإحصائيات العامة</a>
    </div>
    <div class="card-body">
        <div class="stats-boxes">
            <div class="stat-box green">
                <div class="stat-box-value"><?php echo $stats['recipients_count']; ?></div>
                <div class="stat-box-label">المستلمين</div>
            </div>
            <div class="stat-box blue">
                <div class="stat-box-value"><?php echo $stats['read_count']; ?></div>
                <div class="stat-box-label">القراءات</div>
            </div>
            <div class="stat-box orange">
                <div class="stat-box-value"><?php echo $stats['open_rate']; ?>%</div>
                <div class="stat-box-label">نسبة الفتح</div>
            </div>
        </div>
        
        <div class="notification-content-box">
            <p style="margin: 0;"><strong>المحتوى:</strong> <?php echo htmlspecialchars($notification['content']); ?></p>
            <p style="margin: 10px 0 0; color: #888; font-size: 13px;">
                <i class="fas fa-clock"></i> تم الإرسال: <?php echo date('Y/m/d H:i', strtotime($notification['sent_at'])); ?>
            </p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- الإحصائيات العامة -->
<div class="stats-grid" style="margin-bottom: 20px;">
    <div class="stat-card hover-lift">
        <div class="stat-header">
            <div class="stat-icon green"><i class="fas fa-paper-plane"></i></div>
        </div>
        <div class="stat-value"><?php echo $totalSent; ?></div>
        <div class="stat-label">إشعارات مرسلة</div>
    </div>
    
    <div class="stat-card hover-lift">
        <div class="stat-header">
            <div class="stat-icon orange"><i class="fas fa-file-alt"></i></div>
        </div>
        <div class="stat-value"><?php echo $totalDraft; ?></div>
        <div class="stat-label">مسودات</div>
    </div>
    
    <div class="stat-card hover-lift">
        <div class="stat-header">
            <div class="stat-icon blue"><i class="fas fa-clock"></i></div>
        </div>
        <div class="stat-value"><?php echo $totalScheduled; ?></div>
        <div class="stat-label">مجدولة</div>
    </div>
    
    <div class="stat-card hover-lift">
        <div class="stat-header">
            <div class="stat-icon purple"><i class="fas fa-percentage"></i></div>
        </div>
        <div class="stat-value"><?php echo round($avgOpenRate, 1); ?>%</div>
        <div class="stat-label">متوسط نسبة الفتح</div>
    </div>
</div>

<!-- آخر الإشعارات المرسلة -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-history"></i> آخر الإشعارات المرسلة</h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>العنوان</th>
                        <th>النوع</th>
                        <th>المستلمين</th>
                        <th>القراءات</th>
                        <th>نسبة الفتح</th>
                        <th>تاريخ الإرسال</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recentNotifications && $recentNotifications->num_rows > 0): ?>
                    <?php while ($notif = $recentNotifications->fetch_assoc()): 
                        $openRate = $notif['recipients'] > 0 ? round(($notif['reads'] / $notif['recipients']) * 100, 1) : 0;
                        $type_labels = ['general' => 'عام', 'custom' => 'مخصص', 'individual' => 'فردي'];
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars(mb_substr($notif['title'], 0, 30)); ?></strong></td>
                        <td><?php echo $type_labels[$notif['type']] ?? $notif['type']; ?></td>
                        <td><?php echo $notif['recipients']; ?></td>
                        <td><?php echo $notif['reads']; ?></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="flex: 1; height: 8px; background: #eee; border-radius: 4px; overflow: hidden;">
                                    <div style="width: <?php echo $openRate; ?>%; height: 100%; background: <?php echo $openRate >= 50 ? '#10b981' : ($openRate >= 25 ? '#f59e0b' : '#ef4444'); ?>;"></div>
                                </div>
                                <span style="font-size: 12px; color: #666;"><?php echo $openRate; ?>%</span>
                            </div>
                        </td>
                        <td style="color: #888; font-size: 13px;"><?php echo date('Y/m/d H:i', strtotime($notif['sent_at'])); ?></td>
                        <td>
                            <a href="admin_notification_stats.php?id=<?php echo $notif['id']; ?>" class="btn btn-sm btn-outline">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">
                            <i class="fas fa-chart-bar" style="font-size: 40px; margin-bottom: 15px; display: block;"></i>
                            لا توجد إشعارات مرسلة بعد
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Mobile Cards View -->
        <div class="notification-stats-cards">
            <?php 
            // Reset the result pointer
            if ($recentNotifications) $recentNotifications->data_seek(0);
            if ($recentNotifications && $recentNotifications->num_rows > 0): ?>
            <?php while ($notif = $recentNotifications->fetch_assoc()): 
                $openRate = $notif['recipients'] > 0 ? round(($notif['reads'] / $notif['recipients']) * 100, 1) : 0;
                $type_labels = ['general' => 'عام', 'custom' => 'مخصص', 'individual' => 'فردي'];
            ?>
            <div class="stats-card-item">
                <div class="stats-card-header">
                    <strong><?php echo htmlspecialchars(mb_substr($notif['title'], 0, 40)); ?></strong>
                    <span class="badge badge-info"><?php echo $type_labels[$notif['type']] ?? $notif['type']; ?></span>
                </div>
                <div class="stats-card-body">
                    <div class="stats-card-row">
                        <span><i class="fas fa-users"></i> المستلمين: <?php echo $notif['recipients']; ?></span>
                        <span><i class="fas fa-eye"></i> القراءات: <?php echo $notif['reads']; ?></span>
                    </div>
                    <div class="stats-card-progress">
                        <span>نسبة الفتح</span>
                        <div class="progress-bar-wrapper">
                            <div class="progress-bar" style="width: <?php echo $openRate; ?>%; background: <?php echo $openRate >= 50 ? '#10b981' : ($openRate >= 25 ? '#f59e0b' : '#ef4444'); ?>;"></div>
                        </div>
                        <span><?php echo $openRate; ?>%</span>
                    </div>
                    <div class="stats-card-footer">
                        <span style="color: #888; font-size: 12px;"><i class="fas fa-calendar"></i> <?php echo date('Y/m/d H:i', strtotime($notif['sent_at'])); ?></span>
                        <a href="admin_notification_stats.php?id=<?php echo $notif['id']; ?>" class="btn btn-sm btn-outline">
                            <i class="fas fa-eye"></i> تفاصيل
                        </a>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
            <?php else: ?>
            <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                <i class="fas fa-chart-bar" style="font-size: 40px; margin-bottom: 15px; display: block;"></i>
                لا توجد إشعارات مرسلة بعد
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Stats Boxes */
.stats-boxes {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.stat-box {
    text-align: center;
    padding: 20px;
    border-radius: 10px;
}

.stat-box.green { background: #f0fdf4; }
.stat-box.blue { background: #eff6ff; }
.stat-box.orange { background: #fef3c7; }

.stat-box-value {
    font-size: 36px;
    font-weight: 700;
}

.stat-box.green .stat-box-value { color: #10b981; }
.stat-box.blue .stat-box-value { color: #3b82f6; }
.stat-box.orange .stat-box-value { color: #f59e0b; }

.stat-box-label {
    color: #666;
    font-size: 14px;
}

.notification-content-box {
    margin-top: 20px;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 8px;
}

/* Mobile Cards */
.notification-stats-cards {
    display: none;
    padding: 15px;
}

.stats-card-item {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    margin-bottom: 12px;
    overflow: hidden;
}

.stats-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 15px;
    background: var(--bg-hover);
    border-bottom: 1px solid var(--border-color);
}

.stats-card-header strong {
    font-size: 14px;
    color: var(--text-primary);
}

.stats-card-body {
    padding: 15px;
}

.stats-card-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 12px;
    font-size: 13px;
    color: var(--text-secondary);
}

.stats-card-row i {
    margin-left: 5px;
    color: var(--primary);
}

.stats-card-progress {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
    font-size: 12px;
    color: var(--text-muted);
}

.progress-bar-wrapper {
    flex: 1;
    height: 8px;
    background: #eee;
    border-radius: 4px;
    overflow: hidden;
}

.progress-bar {
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s ease;
}

.stats-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 12px;
    border-top: 1px solid var(--border-color);
}

/* Responsive Styles */
@media (max-width: 992px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr) !important;
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr !important;
    }
    
    .stats-boxes {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    
    .stat-box {
        padding: 15px;
    }
    
    .stat-box-value {
        font-size: 28px;
    }
    
    .notification-detail-card .card-header {
        flex-direction: column;
        gap: 10px;
        align-items: flex-start !important;
    }
    
    .notification-detail-card .card-header .btn {
        width: 100%;
        justify-content: center;
    }
    
    /* Hide Table, Show Cards */
    .table-container {
        display: none;
    }
    
    .notification-stats-cards {
        display: block;
    }
    
    .card-header {
        flex-direction: column;
        gap: 10px;
        align-items: flex-start !important;
    }
}

@media (max-width: 576px) {
    .stat-card {
        padding: 15px;
    }
    
    .stat-value {
        font-size: 24px;
    }
    
    .stat-icon {
        width: 40px;
        height: 40px;
        font-size: 18px;
    }
    
    .stats-card-header strong {
        font-size: 13px;
        max-width: 180px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .stats-card-row {
        flex-direction: column;
        gap: 8px;
    }
    
    .notification-content-box {
        padding: 12px;
        font-size: 13px;
    }
}
</style>

<?php include 'includes/admin_footer.php'; ?>

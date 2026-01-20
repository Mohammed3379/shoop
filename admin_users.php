<?php
include '../app/config/database.php';
include 'admin_auth.php';

checkAdminAuth();

// التحقق من صلاحية عرض العملاء
requirePermission('users.view');

$page_title = 'إدارة العملاء';
$page_icon = 'fa-users';

$csrf_token = getAdminCSRF();
$message = '';
$message_type = '';

// صلاحيات المستخدم الحالي
$can_view_details = hasPermission('users.view_details');
$can_edit = hasPermission('users.edit');
$can_delete = hasPermission('users.delete');

// حذف مستخدم
if (isset($_GET['delete']) && isset($_GET['token'])) {
    if (!$can_delete) {
        $message = 'ليس لديك صلاحية حذف العملاء';
        $message_type = 'danger';
    } elseif (!verifyAdminCSRF($_GET['token'])) {
        $message = 'طلب غير صالح!';
        $message_type = 'danger';
    } else {
        $id = intval($_GET['delete']);
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        
        logAdminAction('delete_user', "تم حذف المستخدم رقم: $id");
        $message = 'تم حذف المستخدم بنجاح!';
        $message_type = 'success';
    }
}

// إحصائيات
$total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'] ?? 0;
$active_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'")->fetch_assoc()['count'] ?? 0;

include 'includes/admin_header.php';
?>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?>">
    <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
    <?php echo $message; ?>
</div>
<?php endif; ?>

<!-- إحصائيات سريعة -->
<div class="stats-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 25px;">
    <div class="stat-card hover-lift">
        <div class="stat-header">
            <div class="stat-icon purple"><i class="fas fa-users"></i></div>
        </div>
        <div class="stat-value"><?php echo $total_users; ?></div>
        <div class="stat-label">إجمالي العملاء</div>
    </div>
    
    <div class="stat-card hover-lift">
        <div class="stat-header">
            <div class="stat-icon green"><i class="fas fa-user-check"></i></div>
        </div>
        <div class="stat-value"><?php echo $active_users; ?></div>
        <div class="stat-label">العملاء النشطين</div>
    </div>
    
    <div class="stat-card hover-lift">
        <div class="stat-header">
            <div class="stat-icon orange"><i class="fas fa-user-clock"></i></div>
        </div>
        <div class="stat-value"><?php echo $total_users - $active_users; ?></div>
        <div class="stat-label">الحسابات المعلقة</div>
    </div>
</div>

<!-- جدول العملاء -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-list"></i>
            قائمة العملاء
        </h3>
        <div style="display: flex; gap: 10px;">
            <div style="position: relative;">
                <input type="text" id="search-input" class="form-control" 
                       placeholder="بحث..." 
                       style="padding: 10px 15px; padding-right: 40px; min-width: 250px;">
                <i class="fas fa-search" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
            </div>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table class="admin-table" id="users-table">
                <thead>
                    <tr>
                        <th>العميل</th>
                        <th>البريد الإلكتروني</th>
                        <th>الهاتف</th>
                        <th>تاريخ التسجيل</th>
                        <th>الحالة</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $result = $conn->query("SELECT * FROM users ORDER BY id DESC");
                    
                    if ($result && $result->num_rows > 0):
                        while ($row = $result->fetch_assoc()):
                            $avatar_url = "https://ui-avatars.com/api/?name=" . urlencode($row['full_name']) . "&background=6366f1&color=fff&size=80";
                            $status = $row['status'] ?? 'active';
                    ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <img src="<?php echo $avatar_url; ?>" 
                                     style="width: 42px; height: 42px; border-radius: 10px;">
                                <div>
                                    <div style="font-weight: 600; color: var(--text-primary);">
                                        <?php echo htmlspecialchars($row['full_name']); ?>
                                    </div>
                                    <div style="font-size: 12px; color: var(--text-muted);">
                                        ID: <?php echo $row['id']; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <a href="mailto:<?php echo htmlspecialchars($row['email']); ?>" 
                               style="color: var(--info); text-decoration: none;">
                                <?php echo htmlspecialchars($row['email']); ?>
                            </a>
                        </td>
                        <td>
                            <a href="tel:<?php echo htmlspecialchars($row['phone']); ?>" 
                               style="color: var(--text-secondary); text-decoration: none;">
                                <?php echo htmlspecialchars($row['phone']); ?>
                            </a>
                        </td>
                        <td style="color: var(--text-muted);">
                            <?php echo date('Y/m/d', strtotime($row['created_at'] ?? 'now')); ?>
                        </td>
                        <td>
                            <?php if ($status == 'active'): ?>
                                <span class="badge badge-success">
                                    <i class="fas fa-check-circle"></i>
                                    نشط
                                </span>
                            <?php else: ?>
                                <span class="badge badge-danger">
                                    <i class="fas fa-ban"></i>
                                    محظور
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-btns">
                                <?php if ($can_view_details): ?>
                                <a href="admin_user_details.php?id=<?php echo $row['id']; ?>" 
                                   class="action-btn view" data-tooltip="عرض الملف">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php endif; ?>
                                <?php if ($can_delete): ?>
                                <a href="admin_users.php?delete=<?php echo $row['id']; ?>&token=<?php echo $csrf_token; ?>" 
                                   class="action-btn delete" 
                                   data-confirm="هل تريد حذف هذا العميل نهائياً؟">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php 
                        endwhile;
                    else:
                    ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 60px; color: var(--text-muted);">
                            <i class="fas fa-users" style="font-size: 50px; margin-bottom: 20px; display: block;"></i>
                            لا يوجد عملاء مسجلين
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Mobile Users Cards -->
        <div class="users-cards-mobile">
            <?php 
            // Reset the result pointer
            if ($result) $result->data_seek(0);
            if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()):
                    $avatar_url = "https://ui-avatars.com/api/?name=" . urlencode($row['full_name']) . "&background=6366f1&color=fff&size=80";
                    $status = $row['status'] ?? 'active';
                ?>
                <div class="user-card-mobile">
                    <div class="user-card-header">
                        <img src="<?php echo $avatar_url; ?>" class="user-card-avatar">
                        <div class="user-card-info">
                            <div class="user-card-name"><?php echo htmlspecialchars($row['full_name']); ?></div>
                            <div class="user-card-id">ID: <?php echo $row['id']; ?></div>
                        </div>
                        <?php if ($status == 'active'): ?>
                            <span class="badge badge-success"><i class="fas fa-check-circle"></i> نشط</span>
                        <?php else: ?>
                            <span class="badge badge-danger"><i class="fas fa-ban"></i> محظور</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="user-card-body">
                        <div class="user-card-row">
                            <span><i class="fas fa-envelope"></i></span>
                            <a href="mailto:<?php echo htmlspecialchars($row['email']); ?>"><?php echo htmlspecialchars($row['email']); ?></a>
                        </div>
                        <div class="user-card-row">
                            <span><i class="fas fa-phone"></i></span>
                            <a href="tel:<?php echo htmlspecialchars($row['phone']); ?>"><?php echo htmlspecialchars($row['phone']); ?></a>
                        </div>
                        <div class="user-card-row">
                            <span><i class="fas fa-calendar"></i></span>
                            <span><?php echo date('Y/m/d', strtotime($row['created_at'] ?? 'now')); ?></span>
                        </div>
                    </div>
                    
                    <div class="user-card-actions">
                        <?php if ($can_view_details): ?>
                        <a href="admin_user_details.php?id=<?php echo $row['id']; ?>" class="action-btn view">
                            <i class="fas fa-eye"></i> عرض الملف
                        </a>
                        <?php endif; ?>
                        <?php if ($can_delete): ?>
                        <a href="admin_users.php?delete=<?php echo $row['id']; ?>&token=<?php echo $csrf_token; ?>" 
                           class="action-btn delete" data-confirm="هل تريد حذف هذا العميل نهائياً؟">
                            <i class="fas fa-trash"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state-mobile">
                    <i class="fas fa-users"></i>
                    <p>لا يوجد عملاء مسجلين</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// بحث في الجدول
document.getElementById('search-input').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('#users-table tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});
</script>

<style>
/* تحسينات التجاوب لصفحة العملاء */
@media (max-width: 992px) {
    .admin-table td:nth-child(4),
    .admin-table th:nth-child(4) {
        display: none;
    }
}

@media (max-width: 768px) {
    .stats-grid[style*="grid-template-columns: repeat(3"] {
        grid-template-columns: 1fr !important;
    }
    
    .admin-table td:nth-child(3),
    .admin-table th:nth-child(3) {
        display: none;
    }
    
    #search-input {
        min-width: 100% !important;
    }
    
    .card-header {
        flex-direction: column;
        gap: 15px;
        align-items: stretch !important;
    }
    
    .card-header > div[style*="display: flex"] {
        width: 100%;
    }
    
    .card-header > div[style*="display: flex"] > div {
        width: 100%;
    }
    
    .table-container {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .admin-table {
        min-width: 500px;
    }
    
    .admin-table th,
    .admin-table td {
        padding: 10px 8px;
        font-size: 13px;
    }
    
    .action-btns {
        gap: 5px;
    }
    
    .action-btn {
        width: 32px;
        height: 32px;
        font-size: 12px;
    }
    
    .stat-card {
        padding: 15px;
    }
    
    .stat-value {
        font-size: 24px;
    }
}

@media (max-width: 576px) {
    .admin-table td:nth-child(2),
    .admin-table th:nth-child(2) {
        display: none;
    }
    
    .admin-table td:first-child > div {
        gap: 8px !important;
    }
    
    .admin-table td:first-child img {
        width: 35px !important;
        height: 35px !important;
    }
    
    .admin-table td:first-child > div > div > div:first-child {
        font-size: 13px;
        max-width: 120px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .badge {
        font-size: 10px;
        padding: 4px 8px;
    }
    
    .stat-icon {
        width: 40px;
        height: 40px;
        font-size: 18px;
    }
}

/* Mobile Users Cards */
.users-cards-mobile {
    display: none;
    padding: 15px;
}

.user-card-mobile {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    margin-bottom: 12px;
    overflow: hidden;
}

.user-card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: var(--bg-hover);
    border-bottom: 1px solid var(--border-color);
}

.user-card-avatar {
    width: 45px;
    height: 45px;
    border-radius: 10px;
}

.user-card-info {
    flex: 1;
    min-width: 0;
}

.user-card-name {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 14px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-card-id {
    font-size: 12px;
    color: var(--text-muted);
}

.user-card-body {
    padding: 12px;
}

.user-card-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
    font-size: 13px;
}

.user-card-row:last-child {
    margin-bottom: 0;
}

.user-card-row span:first-child {
    color: var(--primary);
    width: 20px;
    text-align: center;
}

.user-card-row a {
    color: var(--info);
    text-decoration: none;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-card-actions {
    display: flex;
    gap: 8px;
    padding: 12px;
    border-top: 1px solid var(--border-color);
}

.user-card-actions .action-btn {
    flex: 1;
    justify-content: center;
    padding: 8px 12px;
    font-size: 13px;
}

.user-card-actions .action-btn.view {
    background: rgba(99, 102, 241, 0.15);
    color: #6366f1;
}

.empty-state-mobile {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-muted);
}

.empty-state-mobile i {
    font-size: 50px;
    margin-bottom: 15px;
    display: block;
}

@media (max-width: 768px) {
    .table-container {
        display: none;
    }
    
    .users-cards-mobile {
        display: block;
    }
    
    .stats-grid[style*="grid-template-columns: repeat(3"] {
        grid-template-columns: 1fr !important;
    }
    
    .card-header {
        flex-direction: column;
        gap: 15px;
        align-items: stretch !important;
    }
    
    .card-header > div[style*="display: flex"] {
        width: 100%;
    }
    
    .card-header > div[style*="display: flex"] > div {
        width: 100%;
    }
    
    #search-input {
        min-width: 100% !important;
        width: 100% !important;
    }
    
    .stat-card {
        padding: 15px;
    }
    
    .stat-value {
        font-size: 24px;
    }
}

@media (max-width: 480px) {
    .user-card-header {
        padding: 10px;
    }
    
    .user-card-avatar {
        width: 40px;
        height: 40px;
    }
    
    .user-card-name {
        font-size: 13px;
    }
    
    .user-card-body {
        padding: 10px;
    }
    
    .user-card-row {
        font-size: 12px;
    }
    
    .user-card-actions .action-btn {
        font-size: 12px;
        padding: 6px 10px;
    }
    
    .badge {
        font-size: 10px;
        padding: 4px 8px;
    }
    
    .stat-icon {
        width: 40px;
        height: 40px;
        font-size: 18px;
    }
}
</style>

<?php include 'includes/admin_footer.php'; ?>

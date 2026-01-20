<?php
/**
 * سجل نشاطات المسؤولين
 */
include '../app/config/database.php';
include 'admin_auth.php';

checkAdminAuth();

// التحقق من الصلاحية إذا كانت الدالة موجودة
if (function_exists('requirePermission')) {
    requirePermission('logs.view');
}

$page_title = 'سجل النشاطات';
$page_icon = 'fa-history';

// التحقق من وجود جدول السجلات
$table_exists = false;
try {
    $check = $conn->query("SHOW TABLES LIKE 'admin_activity_logs'");
    $table_exists = ($check && $check->num_rows > 0);
} catch (Exception $e) {}

$total = 0;
$total_pages = 0;
$logs = null;

if ($table_exists) {
    // فلترة
    $filter_admin = isset($_GET['admin']) ? intval($_GET['admin']) : 0;
    $filter_action = isset($_GET['action']) ? $_GET['action'] : '';
    $filter_date = isset($_GET['date']) ? $_GET['date'] : '';

    // بناء الاستعلام
    $where = "1=1";
    $params = [];
    $types = "";

    if ($filter_admin) {
        $where .= " AND l.admin_id = ?";
        $params[] = $filter_admin;
        $types .= "i";
    }

    if ($filter_action) {
        $where .= " AND l.action_type = ?";
        $params[] = $filter_action;
        $types .= "s";
    }

    if ($filter_date) {
        $where .= " AND DATE(l.created_at) = ?";
        $params[] = $filter_date;
        $types .= "s";
    }

    // الصفحات
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = 50;
    $offset = ($page - 1) * $per_page;

    // عدد السجلات
    $count_sql = "SELECT COUNT(*) as total FROM admin_activity_logs l WHERE $where";
    $count_stmt = $conn->prepare($count_sql);
    if (!empty($params)) {
        $count_stmt->bind_param($types, ...$params);
    }
    $count_stmt->execute();
    $total = $count_stmt->get_result()->fetch_assoc()['total'];
    $total_pages = ceil($total / $per_page);
} else {
    $filter_admin = 0;
    $filter_action = '';
    $filter_date = '';
    $page = 1;
    $per_page = 50;
    $offset = 0;
}

// جلب السجلات
if ($table_exists) {
    $sql = "SELECT l.*, a.username as admin_name 
            FROM admin_activity_logs l 
            LEFT JOIN admins a ON l.admin_id = a.id 
            WHERE $where 
            ORDER BY l.created_at DESC 
            LIMIT $per_page OFFSET $offset";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $logs = $stmt->get_result();
}

// جلب المسؤولين للفلتر
$admins = $conn->query("SELECT id, username as name FROM admins ORDER BY username");

// أنواع الإجراءات
$action_types = [
    'login_success' => ['name' => 'تسجيل دخول', 'icon' => 'fa-sign-in-alt', 'color' => '#10b981'],
    'login_failed' => ['name' => 'فشل تسجيل الدخول', 'icon' => 'fa-times-circle', 'color' => '#ef4444'],
    'logout' => ['name' => 'تسجيل خروج', 'icon' => 'fa-sign-out-alt', 'color' => '#6b7280'],
    'create' => ['name' => 'إضافة', 'icon' => 'fa-plus-circle', 'color' => '#10b981'],
    'update' => ['name' => 'تعديل', 'icon' => 'fa-edit', 'color' => '#3b82f6'],
    'delete' => ['name' => 'حذف', 'icon' => 'fa-trash', 'color' => '#ef4444'],
    'unauthorized_access' => ['name' => 'محاولة وصول غير مصرح', 'icon' => 'fa-ban', 'color' => '#f59e0b'],
];

include 'includes/admin_header.php';
?>

<style>
.filters-bar {
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-color);
    padding: 20px;
    margin-bottom: 25px;
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    align-items: flex-end;
}

.filter-group {
    flex: 1;
    min-width: 150px;
}

.filter-group label {
    display: block;
    margin-bottom: 8px;
    font-size: 13px;
    color: var(--text-muted);
}

.filter-group select,
.filter-group input {
    width: 100%;
    padding: 10px 15px;
    background: var(--bg-input);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    color: var(--text-primary);
    font-size: 14px;
}

.btn-filter {
    padding: 10px 25px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: var(--radius-md);
    cursor: pointer;
    font-size: 14px;
}

.btn-reset {
    padding: 10px 25px;
    background: var(--bg-hover);
    color: var(--text-secondary);
    border: none;
    border-radius: var(--radius-md);
    cursor: pointer;
    font-size: 14px;
    text-decoration: none;
}

.logs-table {
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-color);
    overflow: hidden;
}

.logs-table table {
    width: 100%;
    border-collapse: collapse;
}

.logs-table th,
.logs-table td {
    padding: 15px 20px;
    text-align: right;
    border-bottom: 1px solid var(--border-color);
}

.logs-table th {
    background: var(--bg-hover);
    font-weight: 600;
    font-size: 13px;
    color: var(--text-muted);
}

.logs-table tr:hover {
    background: var(--bg-hover);
}

.action-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.admin-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}

.admin-cell .avatar {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 14px;
}

.ip-badge {
    font-family: monospace;
    font-size: 12px;
    background: var(--bg-hover);
    padding: 4px 8px;
    border-radius: 4px;
}

.time-cell {
    font-size: 13px;
    color: var(--text-muted);
}

.description-cell {
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 5px;
    margin-top: 25px;
}

.pagination a,
.pagination span {
    padding: 10px 15px;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    color: var(--text-secondary);
    text-decoration: none;
    font-size: 14px;
}

.pagination a:hover {
    background: var(--bg-hover);
}

.pagination .active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

/* تحسينات التجاوب */
@media (max-width: 768px) {
    .filters-bar {
        flex-direction: column;
    }
    
    .filter-group {
        min-width: 100%;
    }
    
    .stats-row {
        grid-template-columns: repeat(2, 1fr) !important;
    }
    
    .logs-table td:nth-child(3),
    .logs-table th:nth-child(3) {
        display: none;
    }
    
    .logs-table td,
    .logs-table th {
        padding: 10px 12px;
        font-size: 12px;
    }
    
    .admin-cell .avatar {
        width: 28px;
        height: 28px;
        font-size: 12px;
    }
}

@media (max-width: 576px) {
    .stats-row {
        grid-template-columns: 1fr !important;
    }
    
    .logs-table td:nth-child(4),
    .logs-table th:nth-child(4) {
        display: none;
    }
    
    .pagination a,
    .pagination span {
        padding: 8px 12px;
        font-size: 12px;
    }
    
    .action-badge {
        padding: 3px 8px;
        font-size: 10px;
    }
}

.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.stat-card {
    background: var(--bg-card);
    border-radius: var(--radius-md);
    border: 1px solid var(--border-color);
    padding: 20px;
    text-align: center;
}

.stat-card .stat-number {
    font-size: 28px;
    font-weight: 700;
    color: var(--primary);
}

.stat-card .stat-label {
    font-size: 13px;
    color: var(--text-muted);
    margin-top: 5px;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-muted);
}

.empty-state i {
    font-size: 50px;
    margin-bottom: 15px;
    opacity: 0.5;
}
</style>

<?php if (!$table_exists): ?>
<div class="alert alert-warning" style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); padding: 20px; border-radius: 10px; margin-bottom: 20px;">
    <i class="fas fa-exclamation-triangle" style="color: #f59e0b;"></i>
    <strong>تنبيه:</strong> جدول سجل النشاطات غير موجود. يرجى تنفيذ ملف <code>permissions_tables.sql</code> في قاعدة البيانات.
</div>
<?php else: ?>
<!-- إحصائيات سريعة -->
<div class="stats-row">
    <div class="stat-card">
        <div class="stat-number"><?php echo $total; ?></div>
        <div class="stat-label">إجمالي السجلات</div>
    </div>
    <?php
    $today_count = 0;
    $login_count = 0;
    $failed_count = 0;
    try {
        $r = $conn->query("SELECT COUNT(*) as c FROM admin_activity_logs WHERE DATE(created_at) = CURDATE()");
        if ($r) $today_count = $r->fetch_assoc()['c'];
        $r = $conn->query("SELECT COUNT(*) as c FROM admin_activity_logs WHERE action_type = 'login_success' AND DATE(created_at) = CURDATE()");
        if ($r) $login_count = $r->fetch_assoc()['c'];
        $r = $conn->query("SELECT COUNT(*) as c FROM admin_activity_logs WHERE action_type IN ('login_failed', 'unauthorized_access') AND DATE(created_at) = CURDATE()");
        if ($r) $failed_count = $r->fetch_assoc()['c'];
    } catch (Exception $e) {}
    ?>
    <div class="stat-card">
        <div class="stat-number"><?php echo $today_count; ?></div>
        <div class="stat-label">نشاطات اليوم</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo $login_count; ?></div>
        <div class="stat-label">تسجيلات دخول اليوم</div>
    </div>
    <div class="stat-card">
        <div class="stat-number" style="color: #ef4444;"><?php echo $failed_count; ?></div>
        <div class="stat-label">محاولات فاشلة</div>
    </div>
</div>
<?php endif; ?>

<!-- شريط الفلترة -->
<form class="filters-bar" method="GET">
    <div class="filter-group">
        <label>المسؤول</label>
        <select name="admin">
            <option value="">الكل</option>
            <?php while ($admin = $admins->fetch_assoc()): ?>
            <option value="<?php echo $admin['id']; ?>" <?php echo $filter_admin == $admin['id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($admin['name']); ?>
            </option>
            <?php endwhile; ?>
        </select>
    </div>
    
    <div class="filter-group">
        <label>نوع الإجراء</label>
        <select name="action">
            <option value="">الكل</option>
            <?php foreach ($action_types as $key => $type): ?>
            <option value="<?php echo $key; ?>" <?php echo $filter_action === $key ? 'selected' : ''; ?>>
                <?php echo $type['name']; ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div class="filter-group">
        <label>التاريخ</label>
        <input type="date" name="date" value="<?php echo $filter_date; ?>">
    </div>
    
    <button type="submit" class="btn-filter">
        <i class="fas fa-filter"></i> فلترة
    </button>
    
    <a href="admin_logs.php" class="btn-reset">إعادة تعيين</a>
</form>

<?php if ($table_exists): ?>
<!-- جدول السجلات -->
<div class="logs-table">
    <?php if ($logs && $logs->num_rows > 0): ?>
    <table>
        <thead>
            <tr>
                <th>المسؤول</th>
                <th>الإجراء</th>
                <th>التفاصيل</th>
                <th>IP</th>
                <th>الوقت</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($log = $logs->fetch_assoc()): 
                $action_info = $action_types[$log['action_type']] ?? ['name' => $log['action_type'], 'icon' => 'fa-circle', 'color' => '#6b7280'];
            ?>
            <tr>
                <td>
                    <div class="admin-cell">
                        <div class="avatar"><?php echo mb_substr($log['admin_name'] ?? '?', 0, 1); ?></div>
                        <span><?php echo htmlspecialchars($log['admin_name'] ?? 'غير معروف'); ?></span>
                    </div>
                </td>
                <td>
                    <span class="action-badge" style="background: <?php echo $action_info['color']; ?>20; color: <?php echo $action_info['color']; ?>;">
                        <i class="fas <?php echo $action_info['icon']; ?>"></i>
                        <?php echo $action_info['name']; ?>
                    </span>
                </td>
                <td class="description-cell" title="<?php echo htmlspecialchars($log['action_description'] ?? ''); ?>">
                    <?php echo htmlspecialchars($log['action_description'] ?? '-'); ?>
                </td>
                <td>
                    <span class="ip-badge"><?php echo htmlspecialchars($log['ip_address']); ?></span>
                </td>
                <td class="time-cell">
                    <?php echo date('Y/m/d H:i:s', strtotime($log['created_at'])); ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div class="empty-state">
        <i class="fas fa-history"></i>
        <h3>لا توجد سجلات</h3>
        <p>لم يتم العثور على أي نشاطات مطابقة للفلتر</p>
    </div>
    <?php endif; ?>
</div>

<!-- الصفحات -->
<?php if ($total_pages > 1): ?>
<div class="pagination">
    <?php if ($page > 1): ?>
    <a href="?page=<?php echo $page - 1; ?>&admin=<?php echo $filter_admin; ?>&action=<?php echo $filter_action; ?>&date=<?php echo $filter_date; ?>">
        <i class="fas fa-chevron-right"></i>
    </a>
    <?php endif; ?>
    
    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
    <a href="?page=<?php echo $i; ?>&admin=<?php echo $filter_admin; ?>&action=<?php echo $filter_action; ?>&date=<?php echo $filter_date; ?>" 
       class="<?php echo $i === $page ? 'active' : ''; ?>">
        <?php echo $i; ?>
    </a>
    <?php endfor; ?>
    
    <?php if ($page < $total_pages): ?>
    <a href="?page=<?php echo $page + 1; ?>&admin=<?php echo $filter_admin; ?>&action=<?php echo $filter_action; ?>&date=<?php echo $filter_date; ?>">
        <i class="fas fa-chevron-left"></i>
    </a>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php endif; // end if table_exists ?>

<?php include 'includes/admin_footer.php'; ?>

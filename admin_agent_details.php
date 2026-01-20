<?php
include '../app/config/database.php';
include 'admin_auth.php';

checkAdminAuth();
$csrf_token = getAdminCSRF();

if (!isset($_GET['id'])) {
    header("Location: admin_agents.php");
    exit();
}

$agent_id = intval($_GET['id']);

// تجميد/تنشيط السائق
if (isset($_GET['action']) && $_GET['action'] == 'toggle_ban' && isset($_GET['token'])) {
    if (verifyAdminCSRF($_GET['token'])) {
        $current_status = $_GET['status'] ?? 'active';
        $new_status = ($current_status == 'active') ? 'banned' : 'active';
        $stmt = $conn->prepare("UPDATE delivery_agents SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $agent_id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: admin_agent_details.php?id=$agent_id");
    exit();
}

// تصفية العهدة
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['clear_cash'])) {
    if (verifyAdminCSRF($_POST['csrf_token'])) {
        $stmt = $conn->prepare("UPDATE orders SET is_paid_to_admin = 1 WHERE delivery_agent_id = ? AND status = 'delivered' AND is_paid_to_admin = 0");
        $stmt->bind_param("i", $agent_id);
        $stmt->execute();
        $stmt->close();
        logAdminAction('clear_cash', "تم استلام العهدة من السائق #$agent_id");
    }
    header("Location: admin_agent_details.php?id=$agent_id&cleared=1");
    exit();
}

// جلب بيانات السائق
$stmt = $conn->prepare("SELECT * FROM delivery_agents WHERE id = ?");
$stmt->bind_param("i", $agent_id);
$stmt->execute();
$agent = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$agent) {
    header("Location: admin_agents.php");
    exit();
}

$page_title = 'ملف السائق: ' . mb_substr($agent['name'], 0, 15);
$page_icon = 'fa-motorcycle';

// الحسابات المالية
$stmt = $conn->prepare("SELECT SUM(total_price) as total FROM orders WHERE delivery_agent_id = ? AND status = 'delivered' AND is_paid_to_admin = 0");
$stmt->bind_param("i", $agent_id);
$stmt->execute();
$cash_on_hand = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE delivery_agent_id = ? AND status = 'delivered'");
$stmt->bind_param("i", $agent_id);
$stmt->execute();
$total_delivered = $stmt->get_result()->fetch_assoc()['count'] ?? 0;
$stmt->close();

include 'includes/admin_header.php';
?>

<?php if (isset($_GET['cleared'])): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i>
    تم استلام العهدة بنجاح!
</div>
<?php endif; ?>

<!-- بطاقة الملف -->
<div class="card" style="margin-bottom: 25px;">
    <div class="card-body">
        <div style="display: flex; gap: 30px; align-items: center; flex-wrap: wrap;">
            <div style="width: 100px; height: 100px; background: linear-gradient(135deg, var(--primary), var(--primary-light)); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-motorcycle" style="font-size: 40px; color: white;"></i>
            </div>
            
            <div style="flex: 1;">
                <h2 style="color: var(--text-primary); margin-bottom: 5px;">
                    <?php echo htmlspecialchars($agent['name']); ?>
                </h2>
                <p style="color: var(--text-muted); margin-bottom: 15px;">
                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($agent['phone']); ?>
                    &nbsp;&nbsp;
                    <i class="fas fa-user-shield"></i> الضامن: <?php echo htmlspecialchars($agent['guarantor_name'] ?? '-'); ?>
                </p>
                
                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <?php $status = $agent['status'] ?? 'active'; ?>
                    <?php if ($status == 'active'): ?>
                        <span class="badge badge-success" style="padding: 8px 15px;">نشط</span>
                        <a href="?id=<?php echo $agent_id; ?>&action=toggle_ban&status=active&token=<?php echo $csrf_token; ?>" 
                           class="btn btn-sm btn-danger" data-confirm="هل تريد تجميد هذا السائق؟">
                            <i class="fas fa-ban"></i> تجميد
                        </a>
                    <?php else: ?>
                        <span class="badge badge-danger" style="padding: 8px 15px;">محظور</span>
                        <a href="?id=<?php echo $agent_id; ?>&action=toggle_ban&status=banned&token=<?php echo $csrf_token; ?>" 
                           class="btn btn-sm btn-success">
                            <i class="fas fa-check"></i> تنشيط
                        </a>
                    <?php endif; ?>
                    
                    <a href="edit_agent.php?id=<?php echo $agent_id; ?>" class="btn btn-sm btn-outline">
                        <i class="fas fa-edit"></i> تعديل البيانات
                    </a>
                </div>
            </div>
            
            <!-- العهدة -->
            <div style="background: <?php echo $cash_on_hand > 0 ? 'rgba(245,158,11,0.15)' : 'rgba(16,185,129,0.15)'; ?>; padding: 25px; border-radius: var(--radius-lg); text-align: center; min-width: 200px;">
                <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 5px;">العهدة الحالية</div>
                <div style="font-size: 32px; font-weight: 700; color: <?php echo $cash_on_hand > 0 ? 'var(--accent)' : 'var(--secondary)'; ?>;">
                    <?php echo formatPrice($cash_on_hand); ?>
                </div>
                <?php if ($cash_on_hand > 0): ?>
                <form method="POST" style="margin-top: 15px;">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <button type="submit" name="clear_cash" class="btn btn-sm btn-success" 
                            onclick="return confirm('هل استلمت المبلغ كاملاً؟')">
                        <i class="fas fa-hand-holding-usd"></i> استلام
                    </button>
                </form>
                <?php else: ?>
                <div style="color: var(--secondary); font-size: 13px; margin-top: 10px;">✅ تمت التصفية</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
    <!-- الوثائق -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-file-image"></i> الوثائق</h3>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
                <?php 
                $docs = [
                    ['driver_id_img', 'بطاقة السائق'],
                    ['guarantor_id_img', 'بطاقة الضامن'],
                    ['guarantee_img', 'ورقة الضمانة']
                ];
                foreach ($docs as $doc):
                ?>
                <div style="text-align: center;">
                    <?php if (!empty($agent[$doc[0]])): ?>
                        <a href="<?php echo $agent[$doc[0]]; ?>" target="_blank">
                            <img src="<?php echo $agent[$doc[0]]; ?>" style="width: 100%; height: 80px; object-fit: cover; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                        </a>
                    <?php else: ?>
                        <div style="height: 80px; background: var(--bg-hover); border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; color: var(--text-muted);">
                            <i class="fas fa-image"></i>
                        </div>
                    <?php endif; ?>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 5px;"><?php echo $doc[1]; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- الإحصائيات -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-chart-bar"></i> الإحصائيات</h3>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div style="background: var(--bg-hover); padding: 20px; border-radius: var(--radius-md); text-align: center;">
                    <div style="font-size: 28px; font-weight: 700; color: var(--secondary);"><?php echo $total_delivered; ?></div>
                    <div style="color: var(--text-muted); font-size: 13px;">طلب تم توصيله</div>
                </div>
                <div style="background: var(--bg-hover); padding: 20px; border-radius: var(--radius-md); text-align: center;">
                    <div style="font-size: 28px; font-weight: 700; color: var(--primary);">
                        <?php 
                        $stmt = $conn->prepare("SELECT SUM(total_price) as total FROM orders WHERE delivery_agent_id = ? AND status = 'delivered'");
                        $stmt->bind_param("i", $agent_id);
                        $stmt->execute();
                        $total_earnings = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
                        $stmt->close();
                        echo formatPrice($total_earnings);
                        ?>
                    </div>
                    <div style="color: var(--text-muted); font-size: 13px;">إجمالي التوصيلات</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- سجل التوصيل -->
<div class="card" style="margin-top: 25px;">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-history"></i> سجل التوصيل</h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>رقم الطلب</th>
                        <th>التاريخ</th>
                        <th>المبلغ</th>
                        <th>حالة التوريد</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $conn->prepare("SELECT * FROM orders WHERE delivery_agent_id = ? ORDER BY id DESC LIMIT 20");
                    $stmt->bind_param("i", $agent_id);
                    $stmt->execute();
                    $history = $stmt->get_result();
                    if ($history && $history->num_rows > 0):
                        while ($order = $history->fetch_assoc()):
                    ?>
                    <tr>
                        <td style="font-weight: 600; color: var(--primary);">#<?php echo $order['id']; ?></td>
                        <td style="color: var(--text-muted);"><?php echo date('Y/m/d', strtotime($order['order_date'])); ?></td>
                        <td style="color: var(--secondary); font-weight: 600;"><?php echo formatPrice($order['total_price']); ?></td>
                        <td>
                            <?php if ($order['status'] == 'delivered'): ?>
                                <?php if ($order['is_paid_to_admin'] ?? 0): ?>
                                    <span class="badge badge-success">تم التوريد</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">في العهدة</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge badge-info"><?php echo $order['status']; ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 40px; color: var(--text-muted);">
                            لا توجد طلبات لهذا السائق
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
@media (max-width: 768px) {
    div[style*="grid-template-columns: 1fr 1fr"] {
        grid-template-columns: 1fr !important;
    }
}

/* تحسينات التجاوب لصفحة تفاصيل السائق */
@media (max-width: 992px) {
    .card:first-of-type .card-body > div[style*="display: flex"][style*="gap: 30px"] {
        flex-direction: column;
        text-align: center;
        align-items: center;
    }
    
    .card:first-of-type .card-body > div[style*="display: flex"][style*="gap: 30px"] > div[style*="flex: 1"] {
        text-align: center;
    }
}

@media (max-width: 768px) {
    .admin-table td:nth-child(4),
    .admin-table th:nth-child(4) {
        display: none;
    }
    
    div[style*="grid-template-columns: repeat(3, 1fr)"] {
        grid-template-columns: repeat(2, 1fr) !important;
    }
}

@media (max-width: 576px) {
    div[style*="grid-template-columns: repeat(3, 1fr)"] {
        grid-template-columns: 1fr !important;
    }
    
    .card:first-of-type .card-body > div[style*="display: flex"][style*="gap: 30px"] > div[style*="min-width: 200px"] {
        min-width: 100% !important;
        padding: 20px !important;
    }
}
</style>

<?php include 'includes/admin_footer.php'; ?>

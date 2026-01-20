<?php
include 'app/config/database.php';

ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_start();

// التحقق من الدخول
if (!isset($_SESSION['agent_id']) || !isset($_SESSION['agent_logged_in'])) {
    header("Location: delivery_login.php");
    exit();
}

// التحقق من انتهاء الجلسة (ساعة واحدة)
if (isset($_SESSION['agent_last_activity']) && (time() - $_SESSION['agent_last_activity'] > 3600)) {
    session_unset();
    session_destroy();
    header("Location: delivery_login.php?expired=1");
    exit();
}
$_SESSION['agent_last_activity'] = time();

$agent_id = $_SESSION['agent_id'];
$agent_name = $_SESSION['agent_name'];

// معالجة الأزرار (مع حماية)
$allowed_actions = ['shipped', 'delivered'];
if (isset($_GET['action']) && isset($_GET['id']) && isset($_GET['token'])) {
    // التحقق من CSRF
    if (!isset($_SESSION['delivery_csrf']) || $_GET['token'] !== $_SESSION['delivery_csrf']) {
        die("طلب غير صالح!");
    }
    
    $order_id = intval($_GET['id']);
    $action = $_GET['action'];
    
    // التحقق من أن الإجراء مسموح
    if (in_array($action, $allowed_actions)) {
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ? AND delivery_agent_id = ?");
        $stmt->bind_param("sii", $action, $order_id, $agent_id);
        $stmt->execute();
        $stmt->close();
    }
    
    header("Location: delivery.php");
    exit();
}

// توليد CSRF token
if (!isset($_SESSION['delivery_csrf'])) {
    $_SESSION['delivery_csrf'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['delivery_csrf'];

// البيانات
$today = date('Y-m-d');
$stats = $conn->query("SELECT COUNT(*) as count, SUM(total_price) as total FROM orders WHERE delivery_agent_id = $agent_id AND status = 'delivered' AND DATE(order_date) = '$today'")->fetch_assoc();
$active_tasks = $conn->query("SELECT * FROM orders WHERE delivery_agent_id = $agent_id AND status IN ('pending', 'shipped') ORDER BY id ASC");
$completed_tasks = $conn->query("SELECT * FROM orders WHERE delivery_agent_id = $agent_id AND status = 'delivered' ORDER BY id DESC LIMIT 5");
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة السائق</title>
    
    <link rel="stylesheet" href="public/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>


</head>
<body>

    <div class="delivery-container">
        <div class="profile-header">
            <div class="avatar"><i class="fas fa-motorcycle"></i></div>
            <div>
                <h3 style="margin:0; color:white; font-size:16px;"><?php echo $agent_name; ?></h3>
                <p style="margin:0; color:#888; font-size:12px;">كابتن توصيل</p>
            </div>
            <a href="delivery_login.php" style="margin-right:auto; color:#dc3545; text-decoration:none; font-size:12px;">خروج</a>
        </div>

        <div class="stats-grid">
            <div class="stat-box">
                <span class="stat-number" style="color:#28a745"><?php echo formatPrice($stats['total'] ?? 0); ?></span>
                <span class="stat-label">كاش اليوم</span>
            </div>
            <div class="stat-box">
                <span class="stat-number" style="color:#ffc107"><?php echo $stats['count'] ?? 0; ?></span>
                <span class="stat-label">مكتملة</span>
            </div>
        </div>

        <h3 style="color:#ffc107; border-right:3px solid #ffc107; padding-right:10px;">المهام الجارية</h3>

        <?php if($active_tasks->num_rows > 0): ?>
            <?php while($row = $active_tasks->fetch_assoc()): ?>
                <?php 
                    $is_shipped = ($row['status'] == 'shipped');
                    $bg_color = $is_shipped ? '#17a2b8' : '#ffc107';
                    $status_txt = $is_shipped ? 'جاري التوصيل' : 'في الانتظار';
                ?>
                
                <div class="task-card">
                    <div class="task-header">
                        <span class="task-id">طلب #<?php echo $row['id']; ?></span>
                        <span class="task-status" style="background:<?php echo $bg_color; ?>"><?php echo $status_txt; ?></span>
                    </div>

                    <div class="task-body">
                        <div style="margin-bottom:15px; color:#ccc; font-size:14px;">
                            <p><i class="fas fa-user"></i> <?php echo $row['customer_name']; ?></p>
                            <p><i class="fas fa-map-marker-alt"></i> <?php echo $row['address']; ?></p>
                        </div>

                        <div class="contact-actions">
                            <a href="tel:<?php echo $row['phone']; ?>" class="btn-call"><i class="fas fa-phone"></i> اتصال</a>
                            <a href="https://wa.me/<?php echo $row['phone']; ?>" target="_blank" class="btn-wa"><i class="fab fa-whatsapp"></i> واتساب</a>
                        </div>

                        <?php if(!empty($row['lat']) && !empty($row['lng'])): ?>
                            <button onclick="openLiveMap(<?php echo $row['lat']; ?>, <?php echo $row['lng']; ?>)" class="btn-map-live">
                                <i class="fas fa-map-marked-alt"></i> فتح الخريطة وبدء الملاحة
                            </button>
                        <?php endif; ?>

                        <div style="background:#1e1e1e; border:1px dashed #555; padding:10px; border-radius:8px; margin-bottom:15px;">
                            <span style="color:#ffc107; font-size:12px; font-weight:bold;">المحتويات:</span>
                            <p style="color:white; font-size:13px; margin:5px 0;"><?php echo $row['products']; ?></p>
                        </div>
                    </div>

                    <?php if(!$is_shipped): ?>
                        <a href="delivery.php?id=<?php echo $row['id']; ?>&action=shipped&token=<?php echo $csrf_token; ?>" class="btn-main btn-start">استلام الطلب</a>
                    <?php else: ?>
                        <a href="delivery.php?id=<?php echo $row['id']; ?>&action=delivered&token=<?php echo $csrf_token; ?>" class="btn-main btn-finish" onclick="return confirm('تأكيد استلام <?php echo formatPrice($row['total_price']); ?>؟')">تم التسليم (تحصيل)</a>
                    <?php endif; ?>
                </div>

            <?php endwhile; ?>
        <?php else: ?>
            <p style="text-align:center; color:#666; padding:30px;">لا توجد مهام جديدة</p>
        <?php endif; ?>
    </div>

    <div id="map-modal">
        <div id="delivery-map"></div>
        <div class="map-controls">
            <button class="close-map-btn" onclick="closeLiveMap()">إغلاق الخريطة</button>
            <a id="gmaps-link" href="#" target="_blank" class="gmaps-btn">
                <i class="fas fa-location-arrow"></i> توجيه Google Maps
            </a>
        </div>
    </div>

    <script>
        let map, agentMarker, customerMarker, routeLine;
        let watchId = null;

        function openLiveMap(custLat, custLng) {
            // 1. إظهار المودال
            document.getElementById('map-modal').style.display = 'flex';
            document.getElementById('gmaps-link').href = `https://www.google.com/maps/dir/?api=1&destination=${custLat},${custLng}`;

            // 2. تهيئة الخريطة (إذا لم تكن موجودة)
            if (!map) {
                map = L.map('delivery-map');
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(map);
            }

            // 3. وضع دبوس العميل (أحمر ثابت)
            // أيقونة حمراء
            var redIcon = new L.Icon({
                iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
                shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41]
            });

            if (customerMarker) map.removeLayer(customerMarker);
            customerMarker = L.marker([custLat, custLng], {icon: redIcon}).addTo(map).bindPopup("منزل العميل").openPopup();

            // 4. تحديد موقع السائق (GPS حي)
            if (navigator.geolocation) {
                // أيقونة زرقاء (السائق)
                var blueIcon = new L.Icon({
                    iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
                    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                    iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41]
                });

                // تتبع حركة السائق
                watchId = navigator.geolocation.watchPosition(function(position) {
                    var lat = position.coords.latitude;
                    var lng = position.coords.longitude;

                    // تحديث مكان السائق
                    if (agentMarker) map.removeLayer(agentMarker);
                    agentMarker = L.marker([lat, lng], {icon: blueIcon}).addTo(map).bindPopup("أنت هنا (الكابتن)");

                    // رسم خط بين السائق والعميل
                    if (routeLine) map.removeLayer(routeLine);
                    routeLine = L.polyline([[lat, lng], [custLat, custLng]], {color: 'blue', weight: 4, opacity: 0.7, dashArray: '10, 10'}).addTo(map);

                    // ضبط الزووم ليشمل الاثنين
                    var group = new L.featureGroup([agentMarker, customerMarker]);
                    map.fitBounds(group.getBounds(), {padding: [50, 50]});

                }, function(error) {
                    alert("يرجى تفعيل الـ GPS لتتبع موقعك على الخريطة.");
                }, {
                    enableHighAccuracy: true
                });
            } else {
                alert("المتصفح لا يدعم GPS");
            }
        }

        function closeLiveMap() {
            document.getElementById('map-modal').style.display = 'none';
            if (watchId) navigator.geolocation.clearWatch(watchId); // إيقاف التتبع لتوفير البطارية
        }
    </script>

</body>
</html>
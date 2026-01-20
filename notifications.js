/**
 * نظام الإشعارات للمستخدم
 * User Notifications System
 * 
 * @package MyShop
 * @version 1.0
 */

const NotificationSystem = {
    // إعدادات
    apiUrl: 'api/user_notifications.php',
    refreshInterval: 30000, // 30 ثانية
    intervalId: null,
    
    /**
     * تهيئة النظام
     */
    init: function() {
        if (typeof activeUserId === 'undefined' || activeUserId === 'guest') {
            return; // المستخدم غير مسجل
        }
        
        this.loadUnreadCount();
        this.bindEvents();
        this.startAutoRefresh();
    },
    
    /**
     * ربط الأحداث
     */
    bindEvents: function() {
        // النقر على أيقونة الإشعارات
        const bellIcon = document.getElementById('notificationBell');
        if (bellIcon) {
            bellIcon.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggleDropdown();
            });
        }
        
        // إغلاق القائمة عند النقر خارجها
        document.addEventListener('click', (e) => {
            const dropdown = document.getElementById('notificationDropdown');
            const bell = document.getElementById('notificationBell');
            if (dropdown && !dropdown.contains(e.target) && !bell.contains(e.target)) {
                dropdown.classList.remove('show');
            }
        });
        
        // تحديد الكل كمقروء
        const markAllBtn = document.getElementById('markAllRead');
        if (markAllBtn) {
            markAllBtn.addEventListener('click', () => this.markAllAsRead());
        }
    },
    
    /**
     * جلب عدد الإشعارات غير المقروءة
     */
    loadUnreadCount: async function() {
        try {
            const response = await fetch(`${this.apiUrl}?action=count`);
            const data = await response.json();
            
            if (data.success) {
                this.updateBadge(data.count);
            }
        } catch (error) {
            console.error('خطأ في جلب عدد الإشعارات:', error);
        }
    },
    
    /**
     * تحديث العداد
     */
    updateBadge: function(count) {
        const badge = document.getElementById('notificationBadge');
        if (badge) {
            badge.textContent = count;
            badge.style.display = count > 0 ? 'flex' : 'none';
        }
    },
    
    /**
     * فتح/إغلاق القائمة المنسدلة
     */
    toggleDropdown: async function() {
        const dropdown = document.getElementById('notificationDropdown');
        if (!dropdown) return;
        
        if (dropdown.classList.contains('show')) {
            dropdown.classList.remove('show');
        } else {
            dropdown.classList.add('show');
            await this.loadNotifications();
        }
    },
    
    /**
     * جلب الإشعارات
     */
    loadNotifications: async function() {
        const list = document.getElementById('notificationList');
        if (!list) return;
        
        list.innerHTML = '<div class="notification-loading"><i class="fas fa-spinner fa-spin"></i> جاري التحميل...</div>';
        
        try {
            const response = await fetch(`${this.apiUrl}?action=get&limit=10`);
            const data = await response.json();
            
            if (data.success) {
                this.renderNotifications(data.notifications);
            } else {
                list.innerHTML = '<div class="notification-empty">حدث خطأ في جلب الإشعارات</div>';
            }
        } catch (error) {
            console.error('خطأ في جلب الإشعارات:', error);
            list.innerHTML = '<div class="notification-empty">حدث خطأ في الاتصال</div>';
        }
    },
    
    /**
     * عرض الإشعارات
     */
    renderNotifications: function(notifications) {
        const list = document.getElementById('notificationList');
        if (!list) return;
        
        if (notifications.length === 0) {
            list.innerHTML = `
                <div class="notification-empty">
                    <i class="fas fa-bell-slash"></i>
                    <p>لا توجد إشعارات</p>
                </div>
            `;
            return;
        }
        
        const categoryIcons = {
            'offer': 'fa-tag',
            'new_product': 'fa-box',
            'cart_reminder': 'fa-shopping-cart',
            'promotional': 'fa-bullhorn'
        };
        
        let html = '';
        notifications.forEach(notif => {
            const icon = categoryIcons[notif.category] || 'fa-bell';
            const readClass = notif.is_read ? 'read' : 'unread';
            const timeAgo = this.timeAgo(notif.created_at);
            
            html += `
                <div class="notification-item ${readClass}" data-id="${notif.notification_id}" onclick="NotificationSystem.markAsRead(${notif.notification_id}, '${notif.link || ''}')">
                    <div class="notification-icon">
                        <i class="fas ${icon}"></i>
                    </div>
                    <div class="notification-content">
                        <h4>${this.escapeHtml(notif.title)}</h4>
                        <p>${this.escapeHtml(notif.content.substring(0, 80))}${notif.content.length > 80 ? '...' : ''}</p>
                        <span class="notification-time">${timeAgo}</span>
                    </div>
                    ${notif.image ? `<img src="${notif.image}" class="notification-image" alt="">` : ''}
                </div>
            `;
        });
        
        list.innerHTML = html;
    },
    
    /**
     * تحديد إشعار كمقروء
     */
    markAsRead: async function(notificationId, link) {
        try {
            const formData = new FormData();
            formData.append('action', 'read');
            formData.append('notification_id', notificationId);
            
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            
            if (data.success) {
                // تحديث العنصر في القائمة
                const item = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
                if (item) {
                    item.classList.remove('unread');
                    item.classList.add('read');
                }
                
                // تحديث العداد
                this.loadUnreadCount();
                
                // الانتقال للرابط إن وجد
                if (link) {
                    window.location.href = link;
                }
            }
        } catch (error) {
            console.error('خطأ في تحديد الإشعار كمقروء:', error);
        }
    },
    
    /**
     * تحديد جميع الإشعارات كمقروءة
     */
    markAllAsRead: async function() {
        try {
            const formData = new FormData();
            formData.append('action', 'read_all');
            
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            
            if (data.success) {
                // تحديث جميع العناصر
                document.querySelectorAll('.notification-item.unread').forEach(item => {
                    item.classList.remove('unread');
                    item.classList.add('read');
                });
                
                // تصفير العداد
                this.updateBadge(0);
                
                // إظهار رسالة
                if (typeof showToast === 'function') {
                    showToast('تم تحديد جميع الإشعارات كمقروءة');
                }
            }
        } catch (error) {
            console.error('خطأ في تحديد الإشعارات كمقروءة:', error);
        }
    },
    
    /**
     * بدء التحديث التلقائي
     */
    startAutoRefresh: function() {
        this.intervalId = setInterval(() => {
            this.loadUnreadCount();
        }, this.refreshInterval);
    },
    
    /**
     * إيقاف التحديث التلقائي
     */
    stopAutoRefresh: function() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
        }
    },
    
    /**
     * حساب الوقت المنقضي
     */
    timeAgo: function(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const seconds = Math.floor((now - date) / 1000);
        
        if (seconds < 60) return 'الآن';
        if (seconds < 3600) return `منذ ${Math.floor(seconds / 60)} دقيقة`;
        if (seconds < 86400) return `منذ ${Math.floor(seconds / 3600)} ساعة`;
        if (seconds < 604800) return `منذ ${Math.floor(seconds / 86400)} يوم`;
        
        return date.toLocaleDateString('ar-SA');
    },
    
    /**
     * تنظيف النص من HTML
     */
    escapeHtml: function(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// تهيئة النظام عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    NotificationSystem.init();
});


/**
 * نظام Push Notifications
 */
const PushNotifications = {
    
    vapidPublicKey: '', // يجب تعيينه من الخادم
    
    /**
     * تهيئة Push Notifications
     */
    init: async function() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            console.log('Push notifications not supported');
            return;
        }
        
        try {
            // تسجيل Service Worker
            // تحديد المسار الصحيح بناءً على موقع الصفحة الحالية
            const basePath = document.querySelector('base')?.href || window.location.origin + window.location.pathname.replace(/\/[^\/]*$/, '/');
            const swPath = basePath + 'public/sw.js';
            const registration = await navigator.serviceWorker.register(swPath).catch(err => {
                console.warn('SW registration failed, trying relative path:', err);
                return navigator.serviceWorker.register('public/sw.js');
            });
            console.log('Service Worker registered');
            
            // التحقق من حالة الاشتراك
            const subscription = await registration.pushManager.getSubscription();
            this.updateUI(subscription !== null);
            
        } catch (error) {
            console.error('Error initializing push:', error);
        }
    },
    
    /**
     * طلب إذن الإشعارات
     */
    requestPermission: async function() {
        const permission = await Notification.requestPermission();
        
        if (permission === 'granted') {
            await this.subscribe();
            return true;
        }
        
        return false;
    },
    
    /**
     * الاشتراك في Push Notifications
     */
    subscribe: async function() {
        try {
            const registration = await navigator.serviceWorker.ready;
            
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.urlBase64ToUint8Array(this.vapidPublicKey)
            });
            
            // إرسال الاشتراك للخادم
            const response = await fetch('api/push_subscribe.php?action=subscribe', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ subscription: subscription.toJSON() })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.updateUI(true);
                if (typeof showToast === 'function') {
                    showToast('تم تفعيل الإشعارات بنجاح');
                }
            }
            
            return data.success;
            
        } catch (error) {
            console.error('Error subscribing:', error);
            return false;
        }
    },
    
    /**
     * إلغاء الاشتراك
     */
    unsubscribe: async function() {
        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();
            
            if (subscription) {
                await subscription.unsubscribe();
            }
            
            // إبلاغ الخادم
            const response = await fetch('api/push_subscribe.php?action=unsubscribe', {
                method: 'POST'
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.updateUI(false);
                if (typeof showToast === 'function') {
                    showToast('تم إلغاء الإشعارات');
                }
            }
            
            return data.success;
            
        } catch (error) {
            console.error('Error unsubscribing:', error);
            return false;
        }
    },
    
    /**
     * تحديث واجهة المستخدم
     */
    updateUI: function(isSubscribed) {
        const btn = document.getElementById('pushToggleBtn');
        if (btn) {
            btn.textContent = isSubscribed ? 'إلغاء الإشعارات' : 'تفعيل الإشعارات';
            btn.dataset.subscribed = isSubscribed;
        }
    },
    
    /**
     * تحويل VAPID key
     */
    urlBase64ToUint8Array: function(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/-/g, '+')
            .replace(/_/g, '/');
        
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        
        return outputArray;
    }
};

// تهيئة Push Notifications
document.addEventListener('DOMContentLoaded', function() {
    if (typeof activeUserId !== 'undefined' && activeUserId !== 'guest') {
        PushNotifications.init();
    }
});

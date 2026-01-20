/**
 * Service Worker للإشعارات
 * Push Notifications Service Worker
 * 
 * @package MyShop
 * @version 1.0
 */

// تثبيت Service Worker
self.addEventListener('install', function(event) {
    console.log('Service Worker installed');
    self.skipWaiting();
});

// تفعيل Service Worker
self.addEventListener('activate', function(event) {
    console.log('Service Worker activated');
    event.waitUntil(clients.claim());
});

// استقبال Push Notification
self.addEventListener('push', function(event) {
    console.log('Push notification received');
    
    let data = {
        title: 'إشعار جديد',
        body: 'لديك إشعار جديد من المتجر',
        icon: '/public/images/icon-192.png',
        badge: '/public/images/badge.png',
        data: { url: '/' }
    };
    
    if (event.data) {
        try {
            data = event.data.json();
        } catch (e) {
            data.body = event.data.text();
        }
    }
    
    const options = {
        body: data.body,
        icon: data.icon || '/public/images/icon-192.png',
        badge: data.badge || '/public/images/badge.png',
        vibrate: [100, 50, 100],
        data: data.data || { url: '/' },
        actions: [
            { action: 'open', title: 'فتح' },
            { action: 'close', title: 'إغلاق' }
        ],
        dir: 'rtl',
        lang: 'ar'
    };
    
    event.waitUntil(
        self.registration.showNotification(data.title, options)
    );
});

// النقر على الإشعار
self.addEventListener('notificationclick', function(event) {
    console.log('Notification clicked');
    
    event.notification.close();
    
    if (event.action === 'close') {
        return;
    }
    
    const url = event.notification.data?.url || '/';
    
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then(function(clientList) {
                // البحث عن نافذة مفتوحة
                for (let client of clientList) {
                    if (client.url.includes(self.location.origin) && 'focus' in client) {
                        client.navigate(url);
                        return client.focus();
                    }
                }
                // فتح نافذة جديدة
                if (clients.openWindow) {
                    return clients.openWindow(url);
                }
            })
    );
});

// إغلاق الإشعار
self.addEventListener('notificationclose', function(event) {
    console.log('Notification closed');
});

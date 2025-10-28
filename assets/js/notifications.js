class DatabaseNotificationManager {
    constructor() {
        this.notifications = [];
        this.isDropdownOpen = false;
        this.pollInterval = 15000; // 15 seconds
        this.apiEndpoint = '../dashboard/controllers/notifications_api.php'; // Your notifications API
        this.userId = this.getCurrentUserId(); // Get from session/localStorage
        
        this.init();
    }

    getCurrentUserId() {
        // Get user ID from session, localStorage, or URL parameter
        // Replace this with your actual method to get current user ID
        const urlParams = new URLSearchParams(window.location.search);
        const userId = urlParams.get('user_id') || localStorage.getItem('user_id') || 1;
        return userId;
    }

    init() {
        this.bindEvents();
        this.loadNotifications();
        this.startPolling();
    }

    bindEvents() {
        const btn = document.querySelector('.notification-btn');
        const dropdown = document.querySelector('.notification-dropdown');
        const markAllRead = document.querySelector('.mark-all-read');

        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggleDropdown();
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.notifications')) {
                this.closeDropdown();
            }
        });

        markAllRead.addEventListener('click', () => {
            this.markAllAsRead();
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isDropdownOpen) {
                this.closeDropdown();
            }
        });
    }

    async fetchNotifications() {
        try {
            const response = await fetch(`${this.apiEndpoint}?user_id=${this.userId}&action=get`);
            const data = await response.json();
            
            if (data.success) {
                return data.notifications;
            } else {
                console.error('Error fetching notifications:', data.message);
                return [];
            }
        } catch (error) {
            console.error('Failed to fetch notifications:', error);
            return [];
        }
    }

    async loadNotifications() {
        const notifications = await this.fetchNotifications();
        this.notifications = notifications;
        this.updateBadge();
        this.renderNotifications();
    }

    startPolling() {
        setInterval(async () => {
            const notifications = await this.fetchNotifications();
            
            // Check for new notifications
            const existingIds = this.notifications.map(n => n.id);
            const newNotifications = notifications.filter(n => !existingIds.includes(n.id));
            
            if (newNotifications.length > 0 || notifications.length !== this.notifications.length) {
                this.notifications = notifications;
                this.updateBadge();
                this.renderNotifications();
                
                // Show browser notification for new items
                if (newNotifications.length > 0) {
                    this.showBrowserNotification(newNotifications[0]);
                }
            }
        }, this.pollInterval);
    }

    showBrowserNotification(notification) {
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification(notification.title, {
                body: notification.message,
                icon: '/favicon.ico'
            });
        } else if ('Notification' in window && Notification.permission !== 'denied') {
            Notification.requestPermission().then(permission => {
                if (permission === 'granted') {
                    new Notification(notification.title, {
                        body: notification.message,
                        icon: '/favicon.ico'
                    });
                }
            });
        }
    }

    toggleDropdown() {
        this.isDropdownOpen = !this.isDropdownOpen;
        const dropdown = document.querySelector('.notification-dropdown');
        
        if (this.isDropdownOpen) {
            dropdown.classList.add('show');
            this.renderNotifications();
        } else {
            dropdown.classList.remove('show');
        }
    }

    closeDropdown() {
        this.isDropdownOpen = false;
        document.querySelector('.notification-dropdown').classList.remove('show');
    }

    updateBadge() {
        const badge = document.querySelector('.notification-badge');
        const unreadCount = this.notifications.filter(n => n.is_read == 0).length;
        
        if (unreadCount > 0) {
            badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    }

    renderNotifications() {
        const list = document.querySelector('.notification-list');
        
        if (this.notifications.length === 0) {
            list.innerHTML = '<div class="no-notifications">Aucune notification</div>';
            return;
        }

        const html = this.notifications.map(notification => `
            <div class="notification-item ${notification.is_read == 0 ? 'unread' : ''}" 
                    data-id="${notification.id}">
                <div class="notification-content">
                    <div class="notification-icon">
                        ${this.getIconByType(notification.type)}
                    </div>
                    <div class="notification-details">
                        <h4 class="notification-title">${notification.title}</h4>
                        <p class="notification-message">${notification.message}</p>
                        <span class="notification-time">${this.formatTimestamp(notification.created_at)}</span>
                    </div>
                </div>
            </div>
        `).join('');

        list.innerHTML = html;

        // Add click handlers
        list.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', () => {
                const id = parseInt(item.dataset.id);
                this.markAsRead(id);
            });
        });
    }

    getIconByType(type) {
        const icons = {
            'order': '🛍️',
            'system': '⚙️',
            'message': '💬',
            'alert': '🚨',
            'success': '✅',
            'warning': '⚠️',
            'info': 'ℹ️',
            'agent': '👤'
        };
        return icons[type] || 'ℹ️';
    }

    formatTimestamp(timestamp) {
        const now = new Date();
        const notificationDate = new Date(timestamp);
        const diff = now - notificationDate;
        const minutes = Math.floor(diff / (1000 * 60));
        const hours = Math.floor(diff / (1000 * 60 * 60));
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));

        if (minutes < 1) return 'À l\'instant';
        if (minutes < 60) return `${minutes}m`;
        if (hours < 24) return `${hours}h`;
        if (days < 7) return `${days}j`;
        
        return notificationDate.toLocaleDateString('fr-FR');
    }

    async markAsRead(id) {
        try {
            const response = await fetch(this.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'mark_read',
                    notification_id: id,
                    user_id: this.userId
                })
            });

            const data = await response.json();
            
            if (data.success) {
                // Update local state
                const notification = this.notifications.find(n => n.id === id);
                if (notification) {
                    notification.is_read = 1;
                    this.updateBadge();
                    this.renderNotifications();
                }
            }
        } catch (error) {
            console.error('Failed to mark notification as read:', error);
        }
    }

    async markAllAsRead() {
        try {
            const response = await fetch(this.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'mark_all_read',
                    user_id: this.userId
                })
            });

            const data = await response.json();
            
            if (data.success) {
                // Update local state
                this.notifications.forEach(n => n.is_read = 1);
                this.updateBadge();
                this.renderNotifications();
            }
        } catch (error) {
            console.error('Failed to mark all notifications as read:', error);
        }
    }

    // Public methods
    static getUnreadCount() {
        return window.dbNotificationManager ? 
            window.dbNotificationManager.notifications.filter(n => n.is_read == 0).length : 0;
    }

    static refresh() {
        if (window.dbNotificationManager) {
            window.dbNotificationManager.loadNotifications();
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.dbNotificationManager = new DatabaseNotificationManager();
});
// Notification bell polling and dropdown
(function () {
    let lastCount = 0;
    let pollInterval = null;

    function startPolling() {
        updateUnreadCount();
        pollInterval = setInterval(updateUnreadCount, 60000);
    }

    function stopPolling() {
        if (pollInterval) clearInterval(pollInterval);
        pollInterval = null;
    }

    function updateUnreadCount() {
        // Only poll if there are badge elements on the page (user is logged in)
        const badges = document.querySelectorAll('[data-notification-count]');
        if (badges.length === 0) return;

        fetch('/notifications/unread-count', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (r) {

                if (!r.ok) return null;
                return r.json();
            })
            .then(function (data) {
                if (!data) return;
                var count = data.count || 0;
                badges.forEach(function (el) {
                    el.textContent = count > 99 ? '99+' : count;
                    el.style.display = count > 0 ? '' : 'none';
                });

                if (count > lastCount && lastCount > 0 && typeof toastr !== 'undefined') {
                    toastr.info('You have new notifications');
                }
                lastCount = count;
            })
            .catch(function () {});
    }

    function loadRecent(container) {
        container.innerHTML = '<div class="px-4 py-8 text-center text-sm text-gray-400">Loading...</div>';
        fetch('/notifications/recent', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (r) {
                if (!r.ok) throw new Error('Failed');
                return r.json();
            })
            .then(function (data) {
                if (!data.notifications || data.notifications.length === 0) {
                    container.innerHTML = '<div class="px-4 py-8 text-center text-sm text-gray-400">No notifications</div>';
                    return;
                }
                container.innerHTML = data.notifications.map(function (n) { return renderNotification(n); }).join('');
            })
            .catch(function () {
                container.innerHTML = '<div class="px-4 py-8 text-center text-sm text-red-400">Failed to load</div>';
            });
    }

    function renderNotification(n) {
        var timeAgo = getRelativeTime(new Date(n.createdAt));
        var unreadDot = n.isRead ? '' : '<span class="w-2 h-2 rounded-full bg-primary-500 flex-shrink-0"></span>';
        var bgClass = n.isRead ? '' : 'bg-primary-50/40';
        var url = n.url || '#';

        return '<a href="' + url + '" data-notification-id="' + n.id + '" class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50 transition-colors ' + bgClass + '" onclick="window._markNotifRead(\'' + n.id + '\')">' +
            '<span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-primary-500 flex-shrink-0">' +
                '<span class="text-xs font-medium text-white">' + (n.actorInitials || '?') + '</span>' +
            '</span>' +
            '<div class="flex-1 min-w-0">' +
                '<p class="text-sm text-gray-900 line-clamp-2">' +
                    (n.actorName ? '<span class="font-semibold">' + escapeHtml(n.actorName) + '</span> ' : '') + escapeHtml(n.typeLabel.toLowerCase()) + (n.entityName ? ' <span class="font-medium">' + escapeHtml(n.entityName) + '</span>' : '') +
                '</p>' +
                '<p class="text-xs text-gray-500 mt-0.5">' + timeAgo + '</p>' +
            '</div>' +
            unreadDot +
        '</a>';
    }

    function getRelativeTime(date) {
        var now = new Date();
        var diff = Math.floor((now - date) / 1000);
        if (diff < 60) return 'just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        if (diff < 172800) return 'yesterday';
        return Math.floor(diff / 86400) + 'd ago';
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    window._markNotifRead = function (id) {
        fetch('/notifications/' + id + '/read', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' }).catch(function () {});
    };

    window._markAllNotifsRead = function () {
        fetch('/notifications/mark-all-read', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function () {
                updateUnreadCount();
                document.querySelectorAll('[data-notification-list]').forEach(function (c) { loadRecent(c); });
            })
            .catch(function () {});
    };

    window._loadNotifDropdown = function (container) {
        loadRecent(container);
    };

    function attachBellListeners() {
        document.querySelectorAll('[data-notif-bell]').forEach(function (btn) {
            if (btn._notifAttached) return;
            btn._notifAttached = true;
            btn.addEventListener('click', function () {
                var container = this.closest('[data-controller]').querySelector('[data-notification-list]');
                if (container) loadRecent(container);
            });
        });
    }

    function init() {

        stopPolling();
        startPolling();
        attachBellListeners();
    }

    // Run init on every possible page load scenario
    document.addEventListener('DOMContentLoaded', init);
    document.addEventListener('turbo:load', init);
    document.addEventListener('turbo:render', init);

    // Also run immediately if DOM is already ready
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        init();
    }
})();

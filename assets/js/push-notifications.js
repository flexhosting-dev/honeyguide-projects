/**
 * Web Push Notifications Module
 * Handles subscription, unsubscription, and permission management for push notifications
 */

/**
 * Check if push notifications are supported in this browser
 */
export function isPushSupported() {
    return (
        'serviceWorker' in navigator &&
        'PushManager' in window &&
        'Notification' in window
    );
}

/**
 * Convert VAPID public key from base64 to Uint8Array
 */
function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding)
        .replace(/\-/g, '+')
        .replace(/_/g, '/');

    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

/**
 * Get base path for API requests
 */
function basePath() {
    return (window.BASE_PATH || '').replace(/\/+$/, '');
}

/**
 * Save subscription to backend
 */
async function saveSubscription(subscription) {
    const response = await fetch(`${basePath()}/push/subscribe`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(subscription)
    });

    if (!response.ok) {
        const error = await response.json();
        throw new Error(error.error || 'Failed to save subscription');
    }

    return response.json();
}

/**
 * Remove subscription from backend
 */
async function removeSubscription(endpoint) {
    const response = await fetch(`${basePath()}/push/unsubscribe`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ endpoint })
    });

    if (!response.ok) {
        const error = await response.json();
        throw new Error(error.error || 'Failed to remove subscription');
    }

    return response.json();
}

/**
 * Subscribe to push notifications
 */
export async function subscribeToPush() {
    if (!isPushSupported()) {
        throw new Error('Push notifications are not supported in this browser');
    }

    // Request notification permission
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
        throw new Error('Notification permission denied');
    }

    // Get service worker registration
    const registration = await navigator.serviceWorker.ready;

    // Check for existing subscription
    let subscription = await registration.pushManager.getSubscription();

    if (!subscription) {
        // Subscribe to push
        const vapidPublicKey = window.VAPID_PUBLIC_KEY;
        if (!vapidPublicKey) {
            throw new Error('VAPID public key not found');
        }

        const convertedVapidKey = urlBase64ToUint8Array(vapidPublicKey);

        subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: convertedVapidKey
        });
    }

    // Save to backend
    const subscriptionData = {
        endpoint: subscription.endpoint,
        keys: {
            p256dh: btoa(String.fromCharCode(...new Uint8Array(subscription.getKey('p256dh')))),
            auth: btoa(String.fromCharCode(...new Uint8Array(subscription.getKey('auth'))))
        }
    };

    await saveSubscription(subscriptionData);

    return subscription;
}

/**
 * Unsubscribe from push notifications
 */
export async function unsubscribeFromPush() {
    if (!isPushSupported()) {
        return;
    }

    const registration = await navigator.serviceWorker.ready;
    const subscription = await registration.pushManager.getSubscription();

    if (subscription) {
        await removeSubscription(subscription.endpoint);
        await subscription.unsubscribe();
    }
}

/**
 * Get current push subscription status
 */
export async function getPushSubscriptionStatus() {
    if (!isPushSupported()) {
        return {
            supported: false,
            permission: 'default',
            subscribed: false
        };
    }

    const registration = await navigator.serviceWorker.ready;
    const subscription = await registration.pushManager.getSubscription();

    return {
        supported: true,
        permission: Notification.permission,
        subscribed: !!subscription,
        subscription: subscription
    };
}

// Expose functions globally for use in templates
window.subscribeToPush = subscribeToPush;
window.unsubscribeFromPush = unsubscribeFromPush;
window.getPushSubscriptionStatus = getPushSubscriptionStatus;
window.isPushSupported = isPushSupported;

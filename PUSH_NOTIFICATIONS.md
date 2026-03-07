# Web Push Notifications

Native push notifications for the Honeyguide Projects PWA.

## Features

- **Native push notifications** on Android, iOS 16.4+, and desktop browsers
- **User preferences** - Control which notification types trigger push notifications
- **Multiple device support** - Receive notifications on all subscribed devices
- **Automatic cleanup** - Stale subscriptions are automatically removed
- **Graceful degradation** - Features work seamlessly even if push is not supported

## Browser Support

| Platform | Browser | Support |
|----------|---------|---------|
| Android | Chrome | ✅ Full support |
| Android | Firefox | ✅ Full support |
| iOS 16.4+ | Safari (PWA) | ✅ Full support (must add to home screen) |
| macOS 13+ | Safari | ✅ Full support |
| Desktop | Chrome/Edge | ✅ Full support |
| Desktop | Firefox | ✅ Full support |

## Setup

### 1. Generate VAPID Keys

VAPID keys are required for Web Push authentication.

```bash
php bin/console app:generate-vapid-keys
```

Copy the output to your `.env` file:

```env
# Web Push Notifications
VAPID_PUBLIC_KEY=your_generated_public_key_here
VAPID_PRIVATE_KEY=your_generated_private_key_here
VAPID_SUBJECT=mailto:noreply@yourdomain.com
```

**Important:**
- Keep the private key secret - never commit it to version control
- The public key is exposed to the frontend (this is normal and safe)
- The VAPID_SUBJECT should be a `mailto:` URL or your website URL

### 2. Run Database Migration

The push subscription table was created during installation:

```bash
php bin/console doctrine:migrations:migrate
```

### 3. Clear Cache

```bash
php bin/console cache:clear
```

## How It Works

### User Flow

1. User visits Settings > Notifications
2. If browser supports push, a "Push" column appears
3. When user enables push for a notification type:
   - Browser requests notification permission
   - Service worker subscribes to push notifications
   - Subscription is saved to the database
4. When a notification is triggered:
   - System checks user's preferences
   - If push is enabled, sends notification to all user's devices
   - Notification appears even if browser/app is closed

### Architecture

```
NotificationService
    ├─> InAppNotificationService (creates Notification entity)
    ├─> EmailNotificationService (sends email)
    └─> PushNotificationService (sends push notification)
            ├─> Retrieves user's PushSubscriptions
            ├─> Uses minishlink/web-push library
            ├─> Sends to browser push service
            └─> Updates subscription last_used_at
```

### Data Flow

1. **Subscription:**
   ```
   Frontend → /push/subscribe → PushNotificationService → Database
   ```

2. **Notification:**
   ```
   Event → NotificationService → PushNotificationService → Browser Push Service → Device
   ```

3. **Click:**
   ```
   Device notification click → Service Worker → Navigate to URL
   ```

## API Endpoints

All endpoints require authentication (`ROLE_USER`).

### GET /push/public-key

Returns the VAPID public key for frontend subscription.

**Response:**
```json
{
  "publicKey": "BGIEdi_MGRGBQRHID86r6V49..."
}
```

### POST /push/subscribe

Save a push subscription for the authenticated user.

**Request:**
```json
{
  "endpoint": "https://fcm.googleapis.com/fcm/send/...",
  "keys": {
    "p256dh": "BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTp...",
    "auth": "tBHItJI5svbpez7KI4CCXg=="
  }
}
```

**Response:**
```json
{
  "success": true
}
```

### POST /push/unsubscribe

Remove a push subscription.

**Request:**
```json
{
  "endpoint": "https://fcm.googleapis.com/fcm/send/..."
}
```

**Response:**
```json
{
  "success": true
}
```

### GET /push/subscription-status

Check if user has active subscriptions.

**Response:**
```json
{
  "hasSubscriptions": true
}
```

### POST /push/test

Send a test notification to the authenticated user.

**Response:**
```json
{
  "success": true,
  "message": "Test notification sent"
}
```

## Database Schema

### push_subscription Table

| Column | Type | Description |
|--------|------|-------------|
| id | UUID | Primary key |
| user_id | UUID | Foreign key to user table |
| endpoint | TEXT | Push service endpoint URL |
| endpoint_hash | VARCHAR(64) | SHA-256 hash of endpoint (for uniqueness) |
| p256dh_key | TEXT | Public key for encryption |
| auth_token | TEXT | Authentication secret |
| user_agent | VARCHAR(500) | Browser user agent |
| created_at | DATETIME | When subscription was created |
| last_used_at | DATETIME | Last time a notification was sent |

**Indexes:**
- `idx_push_subscription_user` on `user_id`
- `idx_push_subscription_last_used` on `last_used_at`
- Unique constraint on `(user_id, endpoint_hash)`

**Cascade delete:** Subscriptions are deleted when user is deleted.

## Notification Payload

Push notifications include the following data:

```json
{
  "title": "John Doe mentioned you",
  "body": "In task: Fix login bug",
  "icon": "/icon-192.png",
  "badge": "/icon-192.png",
  "data": {
    "url": "/projects/abc-123?task=def-456",
    "notificationId": "uuid",
    "timestamp": "2026-03-07T12:00:00Z"
  },
  "tag": "notification-uuid",
  "requireInteraction": false
}
```

## Maintenance

### Cleanup Stale Subscriptions

Remove subscriptions that haven't been used in 30+ days:

```bash
php bin/console app:cleanup-push-subscriptions
```

Or specify a custom number of days:

```bash
php bin/console app:cleanup-push-subscriptions --days=60
```

**Recommended:** Schedule this command to run daily via cron:

```cron
0 3 * * * cd /path/to/project && php bin/console app:cleanup-push-subscriptions
```

### Automatic Cleanup

The system automatically removes subscriptions when:
- The browser/device responds with `410 Gone` (subscription expired)
- The user deletes their account (cascade delete)

## Troubleshooting

### Push column not appearing

**Problem:** The "Push" column doesn't appear in notification settings.

**Cause:** Browser doesn't support push notifications.

**Solution:**
- Use Chrome, Firefox, Edge, or Safari 16.4+
- On iOS: Must add app to home screen for push support
- Check browser console for errors

### Permission denied

**Problem:** User denies notification permission.

**Solution:**
- User must manually reset permission in browser settings
- Chrome: Site Settings > Notifications
- Firefox: Page Info > Permissions > Notifications
- Safari: Preferences > Websites > Notifications

### Notifications not received

**Problem:** User enabled push but not receiving notifications.

**Possible causes:**

1. **Service worker not registered:**
   - Check browser console for service worker errors
   - Verify `sw.js` is accessible at the web root

2. **Subscription not saved:**
   - Check browser network tab for failed `/push/subscribe` request
   - Verify VAPID keys are set in `.env`

3. **User preferences disabled:**
   - Check notification preferences in Settings
   - Verify the specific notification type has push enabled

4. **Stale subscription:**
   - Subscription may have expired (410 Gone)
   - User should re-enable push notifications

5. **HTTPS required:**
   - Push notifications require HTTPS (or localhost for dev)
   - Verify site is served over HTTPS in production

### iOS not working

**Problem:** Push notifications don't work on iOS.

**Requirements:**
- iOS 16.4 or later
- App must be added to home screen (PWA installed)
- Notification permission granted

**Steps:**
1. Open site in Safari
2. Tap Share button → "Add to Home Screen"
3. Open app from home screen
4. Enable push notifications in Settings
5. Grant notification permission when prompted

## Security

### VAPID Keys

- **Private key:** Stored in `.env`, never exposed to frontend or version control
- **Public key:** Exposed to frontend (required for subscription, safe to share)
- **Subject:** Identifies your application to push services

### Subscription Validation

- Only authenticated users can subscribe
- Subscriptions are tied to user accounts
- Endpoint hash prevents duplicate subscriptions

### Payload Security

- No sensitive data in push payload
- Only notification metadata is sent
- User fetches full details after clicking notification

### Rate Limiting

- Browser enforces rate limiting (one notification per tag)
- Push services may throttle excessive requests

## Performance

- **Async sending:** Push notifications don't block the main notification flow
- **Batch processing:** Multiple devices receive notifications in parallel
- **Database indexes:** Fast queries on `user_id` and `last_used_at`
- **Automatic cleanup:** Prevents database growth from stale subscriptions

## Testing

### Manual Testing

1. **Enable push notifications:**
   ```
   Settings > Notifications > Toggle "Push" for "Mentioned"
   ```

2. **Grant browser permission:**
   ```
   Allow notifications when prompted
   ```

3. **Send test notification:**
   ```bash
   curl -X POST http://localhost:8000/push/test \
     -H "Cookie: PHPSESSID=your_session_id"
   ```

4. **Trigger real notification:**
   - Mention yourself in a comment
   - Verify push notification appears

### Browser DevTools

**Check service worker:**
```
Chrome: DevTools > Application > Service Workers
Firefox: about:debugging > This Firefox > Service Workers
```

**View push subscription:**
```javascript
// In browser console
navigator.serviceWorker.ready.then(reg => {
  reg.pushManager.getSubscription().then(sub => console.log(sub));
});
```

**Test notification:**
```javascript
// In browser console
Notification.requestPermission().then(permission => {
  if (permission === 'granted') {
    new Notification('Test', { body: 'This is a test' });
  }
});
```

## Development

### File Structure

```
src/
├── Command/
│   ├── GenerateVapidKeysCommand.php      # Generate VAPID keys
│   └── CleanupPushSubscriptionsCommand.php # Cleanup stale subscriptions
├── Controller/
│   └── PushNotificationController.php     # API endpoints
├── Entity/
│   └── PushSubscription.php               # Subscription entity
├── Enum/
│   └── NotificationType.php               # defaultPush() method
├── Repository/
│   └── PushSubscriptionRepository.php     # Database queries
├── Service/
│   ├── PushNotificationService.php        # Core push logic
│   └── NotificationService.php            # Integration point
└── EventSubscriber/
    └── TwigGlobalsSubscriber.php          # Inject VAPID key to templates

assets/
└── js/
    └── push-notifications.js              # Frontend subscription logic

public/
└── sw.js                                   # Service worker (push handlers)

templates/
├── base.html.twig                         # VAPID key script tag
└── settings/
    └── notifications.html.twig            # Push preferences UI
```

### Adding New Notification Types

1. **Add enum case** in `NotificationType.php`
2. **Update `defaultPush()`** method to set default preference
3. **Update `PushNotificationService`** title/body generators if needed
4. Preferences UI will automatically include the new type

### Custom Notification URLs

Edit `PushNotificationService::getNotificationUrl()`:

```php
private function getNotificationUrl(string $entityType, UuidInterface $entityId, ?array $data = null): string
{
    return match ($entityType) {
        'task' => $this->urlGenerator->generate('app_project_show', ...),
        'project' => $this->urlGenerator->generate('app_project_show', ...),
        'custom_type' => $this->urlGenerator->generate('app_custom_route', ...),
        default => '/',
    };
}
```

## Resources

- [Web Push Protocol (RFC 8030)](https://datatracker.ietf.org/doc/html/rfc8030)
- [VAPID Specification (RFC 8292)](https://datatracker.ietf.org/doc/html/rfc8292)
- [MDN: Push API](https://developer.mozilla.org/en-US/docs/Web/API/Push_API)
- [minishlink/web-push](https://github.com/web-push-libs/web-push-php)
- [Service Worker Cookbook](https://serviceworke.rs/)

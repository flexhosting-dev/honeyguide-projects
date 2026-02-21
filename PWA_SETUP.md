# Progressive Web App (PWA) Setup

Honeyguide Projects is now installable as a Progressive Web App on mobile devices!

## Features

- **Install to Home Screen**: Users can add the app to their home screen on iOS and Android
- **Standalone Mode**: Runs in full-screen mode without browser UI
- **Offline Support**: Basic offline capabilities via Service Worker
- **App-like Experience**: Native-like feel with proper theming and icons

## Files Added

### Core PWA Files
- `src/Controller/ManifestController.php` - Dynamic manifest generation (respects basePath)
- `public/sw.js` - Service Worker for offline support (basePath-aware)
- `public/icon-192.png` - App icon (192x192)
- `public/icon-512.png` - App icon (512x512)

### Template Updates
- `templates/base.html.twig` - Added PWA meta tags and service worker registration

## BasePath Support

**The PWA automatically adapts to your deployment path!**

The manifest and service worker are basePath-aware, meaning they work correctly whether deployed at:
- Development: `dev.flexhosting.co/zohoclone`
- Production: `projects.honeyguide.org/` (root)
- Any subdirectory: `example.com/any/path`

The manifest is generated dynamically by `ManifestController` using Symfony's request basePath, and the service worker detects its own location to determine the correct scope.

### Helper Tools
- `generate_icons.py` - Python script to regenerate icons
- `generate-pwa-icons.html` - Browser-based icon generator
- `generate-icons.js` - Node.js icon generator (requires canvas package)

## How to Install (Users)

### On Android (Chrome/Edge)
1. Open the app in Chrome or Edge
2. Tap the menu (⋮) and select "Add to Home screen" or "Install app"
3. The app will appear on your home screen like a native app

### On iOS (Safari)
1. Open the app in Safari
2. Tap the Share button (□↑)
3. Scroll down and tap "Add to Home Screen"
4. Tap "Add" in the top right corner

## Customization

### Updating App Icons
If you want to change the app icons, you have three options:

1. **Python Script** (Recommended):
   ```bash
   python3 generate_icons.py
   ```

2. **Browser-based Generator**:
   Open `generate-pwa-icons.html` in your browser and download the icons

3. **Manual**:
   Replace `public/icon-192.png` and `public/icon-512.png` with your own icons

### Updating App Metadata
Edit `src/Controller/ManifestController.php` to change:
- `name` and `short_name` - App display names
- `theme_color` - Browser UI color
- `background_color` - Splash screen color
- `description` - App description
- `shortcuts` - Quick actions from home screen icon

Note: The manifest is generated dynamically to support different basePaths, so there is no static `manifest.json` file.

### Service Worker Cache
The service worker (`public/sw.js`) uses a network-first strategy:
- Tries to fetch from network first
- Falls back to cache if offline
- Automatically caches new resources

To update the cache version, change `CACHE_NAME` in `sw.js`:
```javascript
const CACHE_NAME = 'honeyguide-v2'; // increment version number
```

## Testing

1. **Local Testing**:
   - Open in Chrome DevTools → Application → Manifest
   - Check for errors in the manifest
   - Test Service Worker registration

2. **Mobile Testing**:
   - Use Chrome DevTools remote debugging for Android
   - Use Safari Web Inspector for iOS

3. **Lighthouse**:
   - Run Lighthouse in Chrome DevTools
   - Check PWA score and recommendations

## Browser Support

- ✅ Chrome/Edge (Android, Desktop, iOS)
- ✅ Safari (iOS 11.3+, macOS)
- ✅ Firefox (Android)
- ⚠️ Some features may vary by browser

## Notes

- HTTPS is required in production (works on localhost for development)
- Service worker updates automatically when sw.js changes
- Icons should be square and at least 192x192 and 512x512
- The app uses "standalone" display mode for a native-like feel

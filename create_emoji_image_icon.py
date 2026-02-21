#!/usr/bin/env python3
"""
Create PWA icon with actual emoji image on green background
"""

from PIL import Image, ImageDraw
import urllib.request
import io

def create_emoji_image_icon(size, filename):
    # Green background
    img = Image.new('RGB', (size, size), color='#10b981')

    # Download the target emoji from Twemoji (Twitter's open source emoji)
    # Using the direct link to the 🎯 (U+1F3AF) dart emoji SVG/PNG
    emoji_url = "https://em-content.zobj.net/source/apple/391/direct-hit_1f3af.png"

    try:
        # Download the emoji image
        with urllib.request.urlopen(emoji_url) as response:
            emoji_data = response.read()

        # Open the emoji image
        emoji_img = Image.open(io.BytesIO(emoji_data))

        # Resize emoji to fit nicely on the icon (about 60% of icon size)
        emoji_size = int(size * 0.6)
        emoji_img = emoji_img.resize((emoji_size, emoji_size), Image.Resampling.LANCZOS)

        # Position towards the bottom (like the emoji rendering)
        emoji_x = (size - emoji_size) // 2  # Center horizontally
        emoji_y = int(size * 0.28)  # Position towards bottom

        # Paste the emoji onto the green background
        # If emoji has transparency, use it as mask
        if emoji_img.mode == 'RGBA':
            img.paste(emoji_img, (emoji_x, emoji_y), emoji_img)
        else:
            img.paste(emoji_img, (emoji_x, emoji_y))

        # Save
        img.save(filename, 'PNG', optimize=True)
        print(f"✓ Generated {filename} with emoji image")

    except Exception as e:
        print(f"✗ Error downloading/processing emoji: {e}")
        print("Falling back to drawn version...")
        # Fallback to a simple drawn version if download fails
        create_fallback_icon(img, size, filename)

def create_fallback_icon(img, size, filename):
    """Fallback if emoji download fails"""
    draw = ImageDraw.Draw(img)
    center_x = size // 2
    center_y = int(size * 0.58)
    max_radius = int(size * 0.32)

    # Simple bullseye
    draw.ellipse([center_x - max_radius, center_y - max_radius,
                  center_x + max_radius, center_y + max_radius], fill='#dc2626')
    radius2 = int(max_radius * 0.65)
    draw.ellipse([center_x - radius2, center_y - radius2,
                  center_x + radius2, center_y + radius2], fill='white')
    radius3 = int(max_radius * 0.35)
    draw.ellipse([center_x - radius3, center_y - radius3,
                  center_x + radius3, center_y + radius3], fill='#dc2626')

    img.save(filename, 'PNG', optimize=True)
    print(f"✓ Generated {filename} (fallback)")

# Generate both sizes
create_emoji_image_icon(192, 'public/icon-192.png')
create_emoji_image_icon(512, 'public/icon-512.png')

print("\n✓ PWA icons created with real emoji image!")
print("Green background + actual 🎯 emoji = consistent across all devices!")

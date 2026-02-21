#!/usr/bin/env python3
"""
Create PWA icon with actual emoji image on green background
"""

from PIL import Image

def create_emoji_image_icon(size, filename, emoji_path):
    # Green background
    img = Image.new('RGB', (size, size), color='#10b981')

    # Open the emoji image
    emoji_img = Image.open(emoji_path)

    # Resize emoji to fit nicely on the icon (40% of icon size for sharpness)
    emoji_size = int(size * 0.40)
    emoji_img = emoji_img.resize((emoji_size, emoji_size), Image.Resampling.LANCZOS)

    # Convert to RGBA if needed
    if emoji_img.mode != 'RGBA':
        emoji_img = emoji_img.convert('RGBA')

    # Position towards the center-bottom of the green background
    emoji_x = (size - emoji_size) // 2  # Center horizontally
    emoji_y = int(size * 0.40)  # Position higher

    # Paste the emoji onto the green background
    img.paste(emoji_img, (emoji_x, emoji_y), emoji_img)

    # Save
    img.save(filename, 'PNG', optimize=True)
    print(f"✓ Generated {filename} with emoji image")

# Generate both sizes
create_emoji_image_icon(192, 'public/icon-192.png', '/tmp/dart-emoji.png')
create_emoji_image_icon(512, 'public/icon-512.png', '/tmp/dart-emoji.png')

print("\n✓ PWA icons created with real 🎯 emoji!")
print("Green background + actual emoji = consistent across all devices!")

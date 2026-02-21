#!/usr/bin/env python3
"""
Create a proper target/bullseye icon similar to 🎯 emoji
"""

from PIL import Image, ImageDraw

def create_target_icon(size, filename):
    # White background (like the emoji)
    img = Image.new('RGB', (size, size), color='white')
    draw = ImageDraw.Draw(img)

    # Calculate center
    center = size // 2

    # Draw the target circles from outside to inside
    # Outer red ring
    radius1 = int(size * 0.45)
    draw.ellipse(
        [center - radius1, center - radius1,
         center + radius1, center + radius1],
        fill='#ef4444'
    )

    # White ring
    radius2 = int(size * 0.35)
    draw.ellipse(
        [center - radius2, center - radius2,
         center + radius2, center + radius2],
        fill='white'
    )

    # Red ring
    radius3 = int(size * 0.25)
    draw.ellipse(
        [center - radius3, center - radius3,
         center + radius3, center + radius3],
        fill='#ef4444'
    )

    # White ring
    radius4 = int(size * 0.15)
    draw.ellipse(
        [center - radius4, center - radius4,
         center + radius4, center + radius4],
        fill='white'
    )

    # Center red dot
    radius5 = int(size * 0.08)
    draw.ellipse(
        [center - radius5, center - radius5,
         center + radius5, center + radius5],
        fill='#ef4444'
    )

    # Save
    img.save(filename, 'PNG', optimize=True)
    print(f"✓ Generated {filename}")

# Generate both sizes
create_target_icon(192, 'public/icon-192.png')
create_target_icon(512, 'public/icon-512.png')

print("\n✓ Target icons created successfully!")
print("Icons now look like a proper 🎯 bullseye target!")

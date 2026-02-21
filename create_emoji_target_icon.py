#!/usr/bin/env python3
"""
Create a PWA icon with green background and bullseye target with arrow like 🎯 emoji
"""

from PIL import Image, ImageDraw
import math

def create_emoji_target_icon(size, filename):
    # Green background
    img = Image.new('RGB', (size, size), color='#10b981')
    draw = ImageDraw.Draw(img)

    # Position the target towards the bottom (like the emoji rendering)
    center_x = size // 2
    center_y = int(size * 0.58)  # Slightly below center

    # Target size
    max_radius = int(size * 0.32)

    # Rotate the target slightly (15 degrees)
    # Draw the bullseye target (tilted)

    # Outer red ring
    draw.ellipse(
        [center_x - max_radius, center_y - max_radius,
         center_x + max_radius, center_y + max_radius],
        fill='#dc2626'
    )

    # White ring
    radius2 = int(max_radius * 0.7)
    draw.ellipse(
        [center_x - radius2, center_y - radius2,
         center_x + radius2, center_y + radius2],
        fill='white'
    )

    # Red ring
    radius3 = int(max_radius * 0.45)
    draw.ellipse(
        [center_x - radius3, center_y - radius3,
         center_x + radius3, center_y + radius3],
        fill='#dc2626'
    )

    # White center
    radius4 = int(max_radius * 0.2)
    draw.ellipse(
        [center_x - radius4, center_y - radius4,
         center_x + radius4, center_y + radius4],
        fill='white'
    )

    # Draw arrow at an angle (pointing to center)
    # Arrow comes from top-right at about 45 degrees
    angle = -45  # degrees from horizontal
    angle_rad = math.radians(angle)

    arrow_length = int(max_radius * 1.3)
    arrow_start_x = center_x + int(arrow_length * math.cos(angle_rad))
    arrow_start_y = center_y + int(arrow_length * math.sin(angle_rad))

    # Arrow shaft
    shaft_width = max(2, size // 60)
    draw.line(
        [(arrow_start_x, arrow_start_y), (center_x, center_y)],
        fill='#7c2d12',
        width=shaft_width
    )

    # Arrow head (triangle pointing to center)
    head_size = max(6, size // 25)
    # Calculate points for arrow head triangle
    head_angle1 = angle_rad + math.radians(150)
    head_angle2 = angle_rad - math.radians(150)

    point1_x = center_x + int(head_size * math.cos(head_angle1))
    point1_y = center_y + int(head_size * math.sin(head_angle1))
    point2_x = center_x + int(head_size * math.cos(head_angle2))
    point2_y = center_y + int(head_size * math.sin(head_angle2))

    draw.polygon(
        [(center_x, center_y), (point1_x, point1_y), (point2_x, point2_y)],
        fill='#7c2d12'
    )

    # Arrow fletching (back end)
    fletch_size = max(4, size // 35)
    fletch_angle1 = angle_rad + math.radians(30)
    fletch_angle2 = angle_rad - math.radians(30)

    fletch1_x = arrow_start_x + int(fletch_size * math.cos(fletch_angle1))
    fletch1_y = arrow_start_y + int(fletch_size * math.sin(fletch_angle1))
    fletch2_x = arrow_start_x + int(fletch_size * math.cos(fletch_angle2))
    fletch2_y = arrow_start_y + int(fletch_size * math.sin(fletch_angle2))

    draw.polygon(
        [(arrow_start_x, arrow_start_y), (fletch1_x, fletch1_y), (fletch2_x, fletch2_y)],
        fill='#7c2d12'
    )

    # Save
    img.save(filename, 'PNG', optimize=True)
    print(f"✓ Generated {filename}")

# Generate both sizes
create_emoji_target_icon(192, 'public/icon-192.png')
create_emoji_target_icon(512, 'public/icon-512.png')

print("\n✓ Emoji-style target icons created successfully!")
print("Green background with bullseye and arrow at an angle!")

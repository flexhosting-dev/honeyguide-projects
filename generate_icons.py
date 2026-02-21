#!/usr/bin/env python3
"""
Simple PWA icon generator
Creates 192x192 and 512x512 PNG icons for the Honeyguide Projects PWA
"""

try:
    from PIL import Image, ImageDraw, ImageFont
    import os

    def generate_icon(size, filename):
        # Create image with green background
        img = Image.new('RGB', (size, size), color='#10b981')
        draw = ImageDraw.Draw(img)

        # Try to use a larger font for the emoji
        try:
            # Use a system font that supports emoji
            font_size = int(size * 0.6)
            # Common emoji font paths on Linux
            font_paths = [
                '/usr/share/fonts/truetype/noto/NotoColorEmoji.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            ]
            font = None
            for path in font_paths:
                if os.path.exists(path):
                    try:
                        font = ImageFont.truetype(path, font_size)
                        break
                    except:
                        continue

            if not font:
                font = ImageFont.load_default()
        except:
            font = ImageFont.load_default()

        # Draw text (target emoji or fallback text)
        text = "🎯"

        # Calculate text position (center)
        bbox = draw.textbbox((0, 0), text, font=font)
        text_width = bbox[2] - bbox[0]
        text_height = bbox[3] - bbox[1]
        position = ((size - text_width) // 2, (size - text_height) // 2)

        # Draw white text/emoji
        draw.text(position, text, fill='white', font=font)

        # Save
        img.save(filename, 'PNG')
        print(f"✓ Generated {filename}")

    # Generate both sizes
    generate_icon(192, 'public/icon-192.png')
    generate_icon(512, 'public/icon-512.png')

    print("\n✓ PWA icons generated successfully!")

except ImportError:
    print("Error: PIL/Pillow is not installed.")
    print("Please install it with: pip3 install Pillow")
    print("\nAlternatively, open generate-pwa-icons.html in your browser to generate icons manually.")
    exit(1)
except Exception as e:
    print(f"Error: {e}")
    print("\nAlternatively, open generate-pwa-icons.html in your browser to generate icons manually.")
    exit(1)

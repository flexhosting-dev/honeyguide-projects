#!/usr/bin/env node

/**
 * Simple icon generator for PWA
 * Creates 192x192 and 512x512 PNG icons with a target emoji/text
 */

const fs = require('fs');
const { createCanvas } = require('canvas');

const sizes = [192, 512];
const emoji = '🎯'; // Target/bullseye emoji for project management
const bgColor = '#10b981'; // Primary green color

sizes.forEach(size => {
  // Create canvas
  const canvas = createCanvas(size, size);
  const ctx = canvas.getContext('2d');

  // Draw background with rounded corners
  ctx.fillStyle = bgColor;
  ctx.fillRect(0, 0, size, size);

  // Draw emoji in center
  ctx.fillStyle = '#ffffff';
  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';
  ctx.font = `${size * 0.6}px Arial, sans-serif`;
  ctx.fillText(emoji, size / 2, size / 2);

  // Save to file
  const buffer = canvas.toBuffer('image/png');
  fs.writeFileSync(`public/icon-${size}.png`, buffer);
  console.log(`✓ Generated public/icon-${size}.png`);
});

console.log('\n✓ PWA icons generated successfully!');

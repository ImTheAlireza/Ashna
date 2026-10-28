// Check the rendered desktop cover's dimensions, assets, fonts and text bounds.
import { openCover } from './render.mjs';

const { browser, page } = await openCover();
try {
  const report = await page.evaluate(() => {
    const cover = document.getElementById('cover');
    const frame = cover.getBoundingClientRect();
    const issues = [];
    if (frame.width !== 2100 || frame.height !== 1040) {
      issues.push(`Unexpected dimensions: ${frame.width} × ${frame.height}`);
    }
    for (const image of cover.querySelectorAll('img')) {
      if (!image.complete || !image.naturalWidth) issues.push(`Image failed: ${image.getAttribute('src')}`);
    }
    for (const font of ['700 48px Vazirmatn', '700 48px Estedad']) {
      if (!document.fonts.check(font)) issues.push(`Font failed: ${font}`);
    }
    // The illustration has transparent space; its image box intentionally extends
    // beyond its own wrapper, but no content should extend beyond the canvas.
    for (const element of cover.querySelectorAll('.art img, .identity, .footer, .meta, .brand, .subtitle, .english, .features li')) {
      const rect = element.getBoundingClientRect();
      if (rect.left < frame.left - 1 || rect.top < frame.top - 1 ||
          rect.right > frame.right + 1 || rect.bottom > frame.bottom + 1) {
        issues.push(`Outside cover: ${element.className || element.tagName}`);
      }
    }
    for (const element of cover.querySelectorAll('.meta, .subtitle h1, .english, .features li, .footer')) {
      if (element.clientWidth && element.scrollWidth > element.clientWidth + 2) {
        issues.push(`Text overflow: ${element.textContent.trim()}`);
      }
    }
    return { width: frame.width, height: frame.height, features: cover.querySelectorAll('.features li').length, issues };
  });
  console.log(JSON.stringify(report, null, 2));
  if (report.issues.length) process.exitCode = 1;
} finally {
  await browser.close();
}

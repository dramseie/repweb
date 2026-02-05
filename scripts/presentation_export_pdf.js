const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer-core');

const [,, htmlPath, pdfPath] = process.argv;

if (!htmlPath || !pdfPath) {
  console.error('Usage: node scripts/presentation_export_pdf.js <htmlPath> <pdfPath>');
  process.exit(1);
}

const findChromeExecutable = () => {
  const candidates = [
    process.env.CHROME_BIN,
    process.env.PUPPETEER_EXECUTABLE_PATH,
    '/usr/bin/chromium',
    '/usr/bin/chromium-browser',
    '/usr/bin/google-chrome',
    '/usr/bin/google-chrome-stable',
  ].filter(Boolean);

  for (const candidate of candidates) {
    if (fs.existsSync(candidate)) {
      return candidate;
    }
  }
  return null;
};

(async () => {
  const executablePath = findChromeExecutable();
  if (!executablePath) {
    console.error('Chromium executable not found. Set CHROME_BIN or PUPPETEER_EXECUTABLE_PATH.');
    process.exit(1);
  }

  const browser = await puppeteer.launch({
    executablePath,
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });

  try {
    const page = await browser.newPage();
    const target = `file://${path.resolve(htmlPath)}`;
    await page.goto(target, { waitUntil: 'networkidle0' });
    await page.pdf({
      path: pdfPath,
      format: 'A2',
      landscape: true,
      printBackground: true,
      preferCSSPageSize: true,
    });
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error);
  process.exit(1);
});

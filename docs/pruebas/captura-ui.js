// Capturas de pantalla: mockup vs app. Uso: node captura.js <email> <ruta1,ruta2,...> [ancho] [mockupVista1,...]
const puppeteer = require('puppeteer');

(async () => {
  const [email = 'admin@servicecloud.ai', rutas = '/', ancho = '1440', vistasMockup = ''] = process.argv.slice(2);
  const browser = await puppeteer.launch({ args: ['--no-sandbox', '--disable-dev-shm-usage'], executablePath: process.env.CHROME_BIN || undefined });
  const page = await browser.newPage();
  await page.setViewport({ width: parseInt(ancho, 10), height: 900 });
  const espera = (ms) => new Promise(r => setTimeout(r, ms));

  // Mockup
  for (const vista of vistasMockup.split(',').filter(Boolean)) {
    await page.goto('file:///work/docs/mockup/index.html', { waitUntil: 'networkidle0' });
    await page.evaluate(() => localStorage.clear());
    await page.goto('file:///work/docs/mockup/index.html', { waitUntil: 'networkidle0' });
    if (vista !== 'dashboard') {
      await page.evaluate((v) => document.querySelector(`[data-route="${v}"]`)?.click(), vista);
    }
    await espera(900);
    await page.screenshot({ path: `/out/mockup_${vista}_${ancho}.png`, fullPage: false });
  }

  // App
  if (rutas !== '-') {
    await page.goto('http://app:8080/login', { waitUntil: 'networkidle0' });
    await page.screenshot({ path: `/out/app_login_${ancho}.png` });
    await page.type('#email', email);
    await page.type('#password', 'password');
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('button[type=submit]')]);
    for (const ruta of rutas.split(',')) {
      await page.goto('http://app:8080' + ruta, { waitUntil: 'networkidle0' });
      await espera(900);
      const nombre = ruta.replace(/[^a-z0-9]+/gi, '_').replace(/^_|_$/g, '') || 'dashboard';
      await page.screenshot({ path: `/out/app_${nombre}_${ancho}.png`, fullPage: false });
    }
  }
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });

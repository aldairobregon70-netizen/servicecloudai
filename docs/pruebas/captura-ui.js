// Capturas de pantalla: mockup vs app. Uso: node captura.js <email> <ruta1,ruta2,...> [ancho] [mockupVista1,...]
const puppeteer = require('puppeteer');

(async () => {
  const [email = 'admin@servicecloud.ai', rutas = '/', ancho = '1440', vistasMockup = ''] = process.argv.slice(2);
  const browser = await puppeteer.launch({ args: ['--no-sandbox', '--disable-dev-shm-usage'], executablePath: process.env.CHROME_BIN || undefined });
  const page = await browser.newPage();
  await page.setViewport({ width: parseInt(ancho, 10), height: 900 });
  const espera = (ms) => new Promise(r => setTimeout(r, ms));

  // Mockup (vista|selector para clic opcional, p. ej. assets|[data-crud-new])
  for (const entrada of vistasMockup.split(',').filter(Boolean)) {
    const [vista, clic] = entrada.split('|');
    await page.goto('file:///work/docs/mockup/index.html', { waitUntil: 'networkidle0' });
    await page.evaluate(() => localStorage.clear());
    await page.goto('file:///work/docs/mockup/index.html', { waitUntil: 'networkidle0' });
    if (vista !== 'dashboard') {
      await page.evaluate((v) => document.querySelector(`[data-route="${v}"]`)?.click(), vista);
    }
    await espera(600);
    if (clic) { await page.evaluate((s) => document.querySelector(s)?.click(), clic); await espera(500); }
    await page.screenshot({ path: `/out/mockup_${vista}${clic ? '_clic' : ''}_${ancho}.png`, fullPage: false });
  }

  // App
  if (rutas !== '-') {
    await page.goto('http://app:8080/login', { waitUntil: 'networkidle0' });
    await page.screenshot({ path: `/out/app_login_${ancho}.png` });
    await page.type('#email', email);
    await page.type('#password', 'password');
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('button[type=submit]')]);
    // Cada ruta puede llevar un selector para hacer clic antes de capturar: /activos|[data-modal-titulo="Nuevo · Activos"]
    for (const entrada of rutas.split(',')) {
      const [ruta, clic] = entrada.split('|');
      await page.goto('http://app:8080' + ruta, { waitUntil: 'networkidle0' });
      if (clic) { await page.click(clic); }
      await espera(clic ? 1500 : 900);
      const nombre = (ruta.replace(/[^a-z0-9]+/gi, '_').replace(/^_|_$/g, '') || 'dashboard') + (clic ? '_clic' : '');
      await page.screenshot({ path: `/out/app_${nombre}_${ancho}.png`, fullPage: false });
    }
  }
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });

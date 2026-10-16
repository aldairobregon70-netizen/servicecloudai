// Evidencia de punta a punta del caso de ejemplo (fase 5). Uso, desde la raíz del proyecto:
// docker run --rm --network servicecloudai_default -v "$PWD/docs/evidencias:/out" \
//   -v "$PWD/docs/pruebas/evidencia-ia.js:/usr/src/app/evidencia.js:ro" zenika/alpine-chrome:with-puppeteer node evidencia.js
const puppeteer = require('puppeteer');

const CASO = 'La aplicación demora demasiado y finalmente muestra error al consultar clientes';

(async () => {
  const browser = await puppeteer.launch({ args: ['--no-sandbox', '--disable-dev-shm-usage'] });
  const espera = (ms) => new Promise(r => setTimeout(r, ms));
  const entrar = async (email) => {
    const ctx = await browser.createBrowserContext();
    const page = await ctx.newPage();
    await page.setViewport({ width: 1440, height: 1000 });
    await page.goto('http://app:8080/login', { waitUntil: 'networkidle0' });
    await page.type('#email', email);
    await page.type('#password', 'password');
    await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
    return page;
  };

  // 1) El usuario solicitante registra el ticket
  const lucia = await entrar('lucia.vega@andesmarket.pe');
  await lucia.goto('http://app:8080/tickets', { waitUntil: 'networkidle0' });
  await lucia.click('#newTicket');
  await lucia.waitForSelector('#modalBody #f_titulo');
  await lucia.type('#f_titulo', CASO);
  await lucia.type('#f_descripcion', CASO + '. Pasa desde esta mañana en el módulo de clientes del ERP.');
  await lucia.screenshot({ path: '/out/01_registro_ticket.png' });
  await Promise.all([lucia.waitForNavigation({ waitUntil: 'networkidle0' }), lucia.click('#modalFooter button[type=submit]')]);
  const codigo = lucia.url().split('/').pop();
  console.log('Ticket creado:', codigo);

  // 2) El supervisor ve la clasificación, prioridad, causas y artículos sugeridos
  const ana = await entrar('ana.torres@andesmarket.pe');
  await ana.goto('http://app:8080/tickets/' + codigo, { waitUntil: 'networkidle0' });
  await espera(500);
  await ana.screenshot({ path: '/out/02_ticket_clasificado_ia.png', fullPage: true });
  const panel = await ana.$eval('.ai-panel', e => e.innerText);
  console.log('Panel IA:\n' + panel.split('\n').slice(0, 14).join(' | '));

  // 3) Asistente IA sobre el ticket
  await ana.goto('http://app:8080/asistente?nueva=1&prompt=' + encodeURIComponent('Analiza el ticket ' + codigo + ' y dame las causas más probables.'), { waitUntil: 'networkidle0' });
  await ana.click('#sendChat');
  await ana.waitForFunction(() => !document.querySelector('#typingMsg') && document.querySelectorAll('.message').length >= 3, { timeout: 15000 });
  await espera(400);
  await ana.screenshot({ path: '/out/03_asistente_ia.png' });

  // 4) Análisis de logs del mismo síntoma
  await ana.goto('http://app:8080/logs', { waitUntil: 'networkidle0' });
  await ana.click('#analyzeLogs');
  await ana.waitForSelector('#createFromLog', { timeout: 15000 });
  await espera(400);
  await ana.screenshot({ path: '/out/04_analisis_logs.png' });

  // 5) Base de conocimiento con búsqueda semántica
  await ana.goto('http://app:8080/conocimiento?modo=ia&q=' + encodeURIComponent(CASO), { waitUntil: 'networkidle0' });
  await espera(300);
  await ana.screenshot({ path: '/out/05_busqueda_rag.png' });

  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });

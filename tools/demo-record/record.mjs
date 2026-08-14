/**
 * Enregistre une démo réelle page-à-page des 3 dashboards NdamStore.
 *
 * Usage:
 *   cd tools/demo-record
 *   npm install
 *   npx playwright install chromium
 *   node record.mjs
 *
 * Env:
 *   BASE_URL=http://127.0.0.1:8080
 *   ADMIN_EMAIL / ADMIN_PASSWORD
 *   MERCHANT_EMAIL / MERCHANT_PASSWORD
 *   SELLER_EMAIL / SELLER_PASSWORD
 */

import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { spawnSync } from 'child_process';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '../..');
const outDir = path.join(root, 'tools/guide-video-build/demo-real');
const finalMp4 = path.join(root, 'public/videos/ndamstore-guide-nouveaux-utilisateurs.mp4');

const BASE = process.env.BASE_URL || 'http://127.0.0.1:8080';
const accounts = {
  admin: {
    email: process.env.ADMIN_EMAIL || 'admin@boutiquesaas.test',
    password: process.env.ADMIN_PASSWORD || 'admin123',
    label: 'Dashboard Admin',
  },
  merchant: {
    email: process.env.MERCHANT_EMAIL || 'commercant@demo.test',
    password: process.env.MERCHANT_PASSWORD || 'demo1234',
    label: 'Dashboard Entrepreneur',
  },
  seller: {
    email: process.env.SELLER_EMAIL || 'vendeur@demo.test',
    password: process.env.SELLER_PASSWORD || 'demo1234',
    label: 'Dashboard Vendeur',
  },
};

const tours = {
  admin: [
    { path: '/admin', title: 'Vue d’ensemble admin', wait: 2200 },
    { path: '/admin/users', title: 'Utilisateurs', wait: 1800 },
    { path: '/admin/merchants', title: 'Entrepreneurs', wait: 1800 },
    { path: '/admin/shops', title: 'Entreprises', wait: 1800 },
    { path: '/admin/contracts', title: 'Contrats', wait: 1800 },
    { path: '/admin/subscriptions', title: 'Abonnements', wait: 1800 },
    { path: '/admin/fiscalite', title: 'Fiscalité plateforme', wait: 1800 },
    { path: '/admin/activity', title: 'Journal d’activité', wait: 1800 },
  ],
  merchant: [
    { path: '/dashboard', title: 'Tableau de bord', wait: 2200 },
    { path: '/sales/new', title: 'Nouvelle vente (POS)', wait: 2500, alt: ['/vente/nouvelle', '/pos'] },
    { path: '/sales', title: 'Historique des ventes', wait: 1800 },
    { path: '/products', title: 'Produits', wait: 1800 },
    { path: '/categories', title: 'Catégories', wait: 1600 },
    { path: '/suppliers', title: 'Fournisseurs', wait: 1600 },
    { path: '/customers', title: 'Clients', wait: 1800 },
    { path: '/customers/credits', title: 'Crédits clients', wait: 1800 },
    { path: '/stock', title: 'Stock', wait: 1800 },
    { path: '/purchases', title: 'Achats', wait: 1800 },
    { path: '/inventories', title: 'Inventaires', wait: 1600 },
    { path: '/caisse', title: 'Caisse', wait: 1800 },
    { path: '/depenses', title: 'Dépenses', wait: 1600 },
    { path: '/benefices', title: 'Bénéfices', wait: 2000 },
    { path: '/reports', title: 'Rapports', wait: 1800 },
    { path: '/audit', title: 'Journal d’audit', wait: 1600 },
    { path: '/fiscalite', title: 'Fiscalité boutique', wait: 1600 },
    { path: '/shops', title: 'Entreprises', wait: 1600 },
    { path: '/vendeurs', title: 'Équipe / vendeurs', wait: 1800 },
    { path: '/facturation', title: 'Facturation', wait: 1800 },
    { path: '/profile', title: 'Profil', wait: 1400 },
  ],
  seller: [
    { path: '/dashboard', title: 'Tableau de bord vendeur', wait: 2200 },
    { path: '/sales/new', title: 'Point de vente', wait: 2500 },
    { path: '/sales', title: 'Mes ventes', wait: 1800 },
    { path: '/products', title: 'Catalogue (lecture)', wait: 1600 },
    { path: '/customers', title: 'Clients', wait: 1600 },
    { path: '/caisse', title: 'Caisse', wait: 1800 },
    { path: '/profile', title: 'Profil', wait: 1400 },
  ],
};

function findFfmpeg() {
  const which = spawnSync('where.exe', ['ffmpeg'], { encoding: 'utf8' });
  if (which.status === 0) {
    return which.stdout.split(/\r?\n/).find(Boolean);
  }
  const wingetLink = path.join(process.env.LOCALAPPDATA || '', 'Microsoft', 'WinGet', 'Links', 'ffmpeg.exe');
  if (fs.existsSync(wingetLink)) return wingetLink;
  return null;
}

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms));
}

async function login(page, email, password) {
  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle', timeout: 60000 });
  await page.fill('input[name="_username"]', email);
  await page.fill('input[name="_password"]', password);
  await Promise.all([
    page.waitForURL((url) => !String(url).includes('/login'), { timeout: 30000 }),
    page.click('button[type="submit"]'),
  ]);
  await page.waitForLoadState('networkidle').catch(() => {});
  await sleep(1000);
  const url = page.url();
  if (url.includes('/login')) {
    throw new Error(`Échec connexion pour ${email}`);
  }
  console.log(`  connecté → ${url}`);
}

async function logout(page) {
  const form = page.locator('form.sidebar-logout-form, form[action*="logout"]').first();
  if (await form.count()) {
    await Promise.all([
      page.waitForURL(/login|\/$/, { timeout: 20000 }).catch(() => {}),
      form.evaluate((f) => f.submit()),
    ]);
    await sleep(600);
    return;
  }
  await page.goto(`${BASE}/logout`, { waitUntil: 'domcontentloaded' }).catch(() => {});
}

async function visitSteps(page, steps, shotDir, prefix) {
  fs.mkdirSync(shotDir, { recursive: true });
  let i = 0;
  for (const step of steps) {
    i += 1;
    const url = `${BASE}${step.path}`;
    const res = await page.goto(url, { waitUntil: 'networkidle', timeout: 45000 }).catch(() => null);
    const status = res?.status?.() ?? 0;
    if (status >= 400) {
      console.warn(`  ! ${step.path} HTTP ${status} — on continue`);
    }
    // Fermer éventuels toasts / menus
    await page.keyboard.press('Escape').catch(() => {});
    await sleep(Math.max(800, (step.wait || 1500) / 2));
    await page.mouse.wheel(0, 350).catch(() => {});
    await sleep(400);
    await page.mouse.wheel(0, -350).catch(() => {});
    await sleep(Math.max(600, (step.wait || 1500) / 2));
    const file = path.join(shotDir, `${prefix}-${String(i).padStart(2, '0')}.png`);
    await page.screenshot({ path: file, fullPage: false });
    const bodyText = await page.locator('body').innerText().catch(() => '');
    if (/No route found|404 Not Found|SmartDeal/i.test(bodyText)) {
      console.warn(`  ! page invalide: ${step.path}`);
    } else {
      console.log(`  ✓ ${step.title} → ${path.basename(file)}`);
    }
  }
}

function makeTitlePng(phpBin, outFile, title, subtitle) {
  const script = `
  $w=1280;$h=720;
  $im=imagecreatetruecolor($w,$h);
  for($y=0;$y<$h;$y++){
    $t=$y/($h-1);
    $col=imagecolorallocate($im,(int)(10+(20-10)*$t),(int)(63+(122-63)*$t),(int)(55+(106-55)*$t));
    imageline($im,0,$y,$w,$y,$col);
  }
  $white=imagecolorallocate($im,244,250,247);
  $muted=imagecolorallocate($im,190,220,210);
  $font='C:/Windows/Fonts/segoeuib.ttf';
  $fontR='C:/Windows/Fonts/segoeui.ttf';
  if(!is_file($font)) $font='C:/Windows/Fonts/arialbd.ttf';
  if(!is_file($fontR)) $fontR='C:/Windows/Fonts/arial.ttf';
  imagettftext($im,18,0,72,120,$muted,$fontR,'NDAMSTORE · DÉMO RÉELLE');
  imagettftext($im,44,0,72,260,$white,$font,${JSON.stringify(title)});
  imagettftext($im,22,0,72,330,$muted,$fontR,${JSON.stringify(subtitle)});
  imagepng($im,${JSON.stringify(outFile)});
  `;
  const tmp = path.join(outDir, '_title.php');
  fs.writeFileSync(tmp, `<?php\n${script}`);
  spawnSync(phpBin, [tmp], { stdio: 'inherit' });
}

function ffmpegRun(ffmpeg, args) {
  const r = spawnSync(ffmpeg, args, { encoding: 'utf8' });
  if (r.status !== 0) {
    console.error(r.stderr?.slice(-1500));
    throw new Error(`ffmpeg failed: ${args.join(' ')}`);
  }
}

async function main() {
  fs.rmSync(outDir, { recursive: true, force: true });
  fs.mkdirSync(outDir, { recursive: true });

  const ffmpeg = findFfmpeg();
  if (!ffmpeg) throw new Error('ffmpeg introuvable');
  const phpBin = process.env.PHP_BINARY || 'php';

  console.log(`BASE_URL=${BASE}`);
  console.log('Lancement Chromium + enregistrement…');

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    deviceScaleFactor: 1,
    locale: 'fr-FR',
    recordVideo: {
      dir: path.join(outDir, 'raw'),
      size: { width: 1280, height: 720 },
    },
  });
  const page = await context.newPage();

  // Intro
  await page.goto(`${BASE}/`, { waitUntil: 'networkidle', timeout: 60000 });
  await sleep(2000);
  await page.goto(`${BASE}/guide`, { waitUntil: 'networkidle', timeout: 60000 });
  await sleep(1500);

  for (const key of ['admin', 'merchant', 'seller']) {
    const acc = accounts[key];
    console.log(`\n=== ${acc.label} (${acc.email}) ===`);
    await login(page, acc.email, acc.password);
    await visitSteps(page, tours[key], path.join(outDir, 'shots', key), key);
    await logout(page);
  }

  await context.close();
  await browser.close();

  // Playwright écrit 1 vidéo webm/mp4 par page context — récupérer le fichier
  const rawDir = path.join(outDir, 'raw');
  const rawVideos = fs.readdirSync(rawDir).filter((f) => f.endsWith('.webm') || f.endsWith('.mp4'));
  if (!rawVideos.length) throw new Error('Aucune vidéo Playwright générée');
  const rawPath = path.join(rawDir, rawVideos[0]);
  console.log(`\nVidéo brute: ${rawPath}`);

  // Cartons titre
  const titles = path.join(outDir, 'titles');
  fs.mkdirSync(titles, { recursive: true });
  makeTitlePng(phpBin, path.join(titles, 't0.png'), 'Guide NdamStore', 'Démonstration réelle des 3 espaces');
  makeTitlePng(phpBin, path.join(titles, 't1.png'), '1 · Admin', 'Pilotage de la plateforme');
  makeTitlePng(phpBin, path.join(titles, 't2.png'), '2 · Entrepreneur', 'Gestion complète de la boutique');
  makeTitlePng(phpBin, path.join(titles, 't3.png'), '3 · Vendeur', 'Caisse, ventes et clients');
  makeTitlePng(phpBin, path.join(titles, 't4.png'), 'Merci', 'Guide : /guide  ·  Support NdamStore');

  const segs = path.join(outDir, 'segs');
  fs.mkdirSync(segs, { recursive: true });
  const concat = [];
  const titleFiles = ['t0', 't1', 't2', 't3', 't4'];
  for (const [idx, name] of titleFiles.entries()) {
    const out = path.join(segs, `title-${idx}.mp4`);
    ffmpegRun(ffmpeg, [
      '-y', '-loop', '1', '-i', path.join(titles, `${name}.png`),
      '-f', 'lavfi', '-i', 'anullsrc=channel_layout=stereo:sample_rate=44100',
      '-c:v', 'libx264', '-t', '2.5', '-pix_fmt', 'yuv420p', '-c:a', 'aac', '-shortest', out,
    ]);
    concat.push(out);
  }

  // Convertir la démo brute en mp4 normalisé
  const demoNorm = path.join(segs, 'demo.mp4');
  ffmpegRun(ffmpeg, [
    '-y', '-i', rawPath,
    '-vf', 'scale=1280:720:force_original_aspect_ratio=decrease,pad=1280:720:(ow-iw)/2:(oh-ih)/2,fps=30',
    '-c:v', 'libx264', '-c:a', 'aac', '-pix_fmt', 'yuv420p', demoNorm,
  ]);

  // Ordre final: intro + (admin title is already in recording flow) + demo + outro
  // On intercale les cartons dans le concat: t0, demo, t4  — et t1-t3 sont aussi en début de sections
  // Pour simplicité: t0 + demo + t4 (les sections sont visibles dans la capture)
  const listFile = path.join(outDir, 'concat.txt');
  const finalParts = [concat[0], demoNorm, concat[4]];
  fs.writeFileSync(
    listFile,
    finalParts.map((p) => `file '${p.replace(/\\/g, '/')}'`).join('\n')
  );

  fs.mkdirSync(path.dirname(finalMp4), { recursive: true });
  ffmpegRun(ffmpeg, [
    '-y', '-f', 'concat', '-safe', '0', '-i', listFile,
    '-c:v', 'libx264', '-c:a', 'aac', '-pix_fmt', 'yuv420p', finalMp4,
  ]);

  // Poster = première capture admin
  const posterSrc = path.join(outDir, 'shots', 'admin', 'admin-01.png');
  const posterDst = path.join(root, 'public/videos/ndamstore-guide-poster.jpg');
  if (fs.existsSync(posterSrc)) {
    spawnSync(phpBin, [
      '-r',
      `$im=imagecreatefrompng(${JSON.stringify(posterSrc)}); imagejpeg($im, ${JSON.stringify(posterDst)}, 85);`,
    ]);
  }

  const size = fs.statSync(finalMp4).size;
  console.log(`\nVIDEO OK: ${finalMp4}`);
  console.log(`Taille: ${(size / 1024 / 1024).toFixed(1)} Mo`);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});

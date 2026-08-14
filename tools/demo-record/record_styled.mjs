/**
 * Démo stylée page-à-page + voix française (Hortense).
 *
 *   $env:BASE_URL='http://127.0.0.1:8091'
 *   node record_styled.mjs
 */

import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { spawnSync } from 'child_process';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '../..');
const outDir = path.join(root, 'tools/guide-video-build/demo-styled');
const finalMp4 = path.join(root, 'public/videos/ndamstore-guide-nouveaux-utilisateurs.mp4');
const posterJpg = path.join(root, 'public/videos/ndamstore-guide-poster.jpg');
const BASE = process.env.BASE_URL || 'http://127.0.0.1:8091';

const accounts = {
  admin: {
    email: process.env.ADMIN_EMAIL || 'admin@boutiquesaas.test',
    password: process.env.ADMIN_PASSWORD || 'admin123',
  },
  merchant: {
    email: process.env.MERCHANT_EMAIL || 'commercant@demo.test',
    password: process.env.MERCHANT_PASSWORD || 'demo1234',
  },
  seller: {
    email: process.env.SELLER_EMAIL || 'vendeur@demo.test',
    password: process.env.SELLER_PASSWORD || 'demo1234',
  },
};

/** @type {Array<{role:string, path:string, title:string, voice:string}>} */
const scenes = [
  {
    role: 'intro',
    path: '/',
    title: 'Bienvenue',
    voice:
      'Bienvenue sur NdamStore. Voici une démonstration réelle et moderne des trois espaces : administrateur, entrepreneur, et vendeur.',
  },
  {
    role: 'intro',
    path: '/guide',
    title: 'Guide',
    voice:
      'Le guide d’utilisation reste disponible à tout moment pour retrouver chaque module, le glossaire, et le support.',
  },
  // Admin
  {
    role: 'admin',
    path: '/admin',
    title: 'Admin · Vue d’ensemble',
    voice:
      'Espace administrateur. La vue d’ensemble donne le pilotage de la plateforme : entrepreneurs, entreprises, abonnements et activité.',
  },
  {
    role: 'admin',
    path: '/admin/users',
    title: 'Admin · Utilisateurs',
    voice:
      'Gérez les comptes utilisateurs, activez ou suspendez un accès, et suivez qui se connecte à la plateforme.',
  },
  {
    role: 'admin',
    path: '/admin/merchants',
    title: 'Admin · Entrepreneurs',
    voice:
      'Créez et suivez les entrepreneurs. C’est ici que vous ouvrez un compte commerçant et préparez son abonnement.',
  },
  {
    role: 'admin',
    path: '/admin/shops',
    title: 'Admin · Entreprises',
    voice:
      'Visualisez toutes les boutiques rattachées. Vous contrôlez l’état des entreprises sur la plateforme.',
  },
  {
    role: 'admin',
    path: '/admin/contracts',
    title: 'Admin · Contrats',
    voice:
      'Rédigez, partagez et suivez les contrats. L’entrepreneur les retrouve ensuite sur son tableau de bord.',
  },
  {
    role: 'admin',
    path: '/admin/subscriptions',
    title: 'Admin · Abonnements',
    voice:
      'Suivez les plans Basique et Pro, enregistrez les paiements Wave ou Orange Money, et gardez la facturation à jour.',
  },
  {
    role: 'admin',
    path: '/admin/fiscalite',
    title: 'Admin · Fiscalité',
    voice:
      'Paramétrez la fiscalité de référence de la plateforme : base pour les boutiques et la conformité.',
  },
  {
    role: 'admin',
    path: '/admin/activity',
    title: 'Admin · Journal',
    voice:
      'Le journal d’activité trace les actions importantes. Utile pour l’audit et le support.',
  },
  // Merchant
  {
    role: 'merchant',
    path: '/dashboard',
    title: 'Entrepreneur · Tableau de bord',
    voice:
      'Espace entrepreneur. Le tableau de bord résume vos ventes, votre progression de démarrage, et les accès rapides.',
  },
  {
    role: 'merchant',
    path: '/sales/new',
    title: 'Entrepreneur · Point de vente',
    voice:
      'Le point de vente moderne : recherchez un produit, scannez un code-barres, appliquez une remise, puis encaissez en espèces, Wave, Orange Money ou crédit.',
  },
  {
    role: 'merchant',
    path: '/sales',
    title: 'Entrepreneur · Ventes',
    voice:
      'Retrouvez l’historique des ventes, rouvrez un ticket, et suivez le chiffre d’affaires jour après jour.',
  },
  {
    role: 'merchant',
    path: '/products',
    title: 'Entrepreneur · Produits',
    voice:
      'Votre catalogue produits. Renseignez toujours le prix d’achat et le prix de vente pour des bénéfices fiables.',
  },
  {
    role: 'merchant',
    path: '/categories',
    title: 'Entrepreneur · Catégories',
    voice:
      'Organisez le catalogue avec des catégories claires : plus rapide en caisse, plus lisible en stock.',
  },
  {
    role: 'merchant',
    path: '/suppliers',
    title: 'Entrepreneur · Fournisseurs',
    voice:
      'Centralisez vos fournisseurs pour préparer les commandes et les réceptions d’achat.',
  },
  {
    role: 'merchant',
    path: '/customers',
    title: 'Entrepreneur · Clients',
    voice:
      'Les fiches clients permettent le crédit, le suivi des soldes, et une relation commerciale plus professionnelle.',
  },
  {
    role: 'merchant',
    path: '/customers/credits',
    title: 'Entrepreneur · Crédits',
    voice:
      'Le registre des crédits montre qui doit quoi. Enregistrez les paiements partiels en quelques clics.',
  },
  {
    role: 'merchant',
    path: '/stock',
    title: 'Entrepreneur · Stock',
    voice:
      'Pilotez les niveaux de stock, les alertes bas, et les mouvements pour éviter les ruptures.',
  },
  {
    role: 'merchant',
    path: '/purchases',
    title: 'Entrepreneur · Achats',
    voice:
      'Créez des bons d’achat fournisseur et recevez la marchandise, même partiellement.',
  },
  {
    role: 'merchant',
    path: '/inventories',
    title: 'Entrepreneur · Inventaires',
    voice:
      'Lancez un inventaire pour réconcilier le stock théorique et le stock réel.',
  },
  {
    role: 'merchant',
    path: '/caisse',
    title: 'Entrepreneur · Caisse',
    voice:
      'Ouvrez la session de caisse avant de vendre, puis clôturez le soir pour contrôler les écarts.',
  },
  {
    role: 'merchant',
    path: '/depenses',
    title: 'Entrepreneur · Dépenses',
    voice:
      'Saisissez les dépenses de la boutique : elles viennent en déduction pour un bénéfice net plus juste.',
  },
  {
    role: 'merchant',
    path: '/benefices',
    title: 'Entrepreneur · Bénéfices',
    voice:
      'Le module bénéfices calcule la marge : chiffre d’affaires moins coût d’achat, puis dépenses. Filtrez par période et exportez.',
  },
  {
    role: 'merchant',
    path: '/reports',
    title: 'Entrepreneur · Rapports',
    voice:
      'Les rapports consolident C A, T V A, et exports utiles pour le suivi et la fiscalité.',
  },
  {
    role: 'merchant',
    path: '/vendeurs',
    title: 'Entrepreneur · Équipe',
    voice:
      'Invitez caissiers, responsables ou magasiniers. Chaque rôle a des permissions adaptées.',
  },
  {
    role: 'merchant',
    path: '/facturation',
    title: 'Entrepreneur · Facturation',
    voice:
      'Suivez votre abonnement NdamStore et les instructions de paiement Wave ou Orange Money.',
  },
  // Seller
  {
    role: 'seller',
    path: '/dashboard',
    title: 'Vendeur · Tableau de bord',
    voice:
      'Espace vendeur. Une interface épurée, centrée sur la vente et la caisse.',
  },
  {
    role: 'seller',
    path: '/sales/new',
    title: 'Vendeur · Caisse / POS',
    voice:
      'Le vendeur encaissée rapidement : panier, paiement, ticket. Simple, clair, et pensé pour le quotidien en boutique.',
  },
  {
    role: 'seller',
    path: '/sales',
    title: 'Vendeur · Ventes',
    voice:
      'Il consulte ses ventes récentes pour vérifier un ticket ou un montant.',
  },
  {
    role: 'seller',
    path: '/products',
    title: 'Vendeur · Catalogue',
    voice:
      'Le catalogue est en lecture pour retrouver un prix ou un code produit sans modifier le stock.',
  },
  {
    role: 'seller',
    path: '/customers',
    title: 'Vendeur · Clients',
    voice:
      'Accès aux clients pour une vente à crédit ou pour retrouver une fiche.',
  },
  {
    role: 'seller',
    path: '/caisse',
    title: 'Vendeur · Session de caisse',
    voice:
      'La session de caisse reste obligatoire : ouverture le matin, clôture le soir.',
  },
  {
    role: 'outro',
    path: '/guide#video',
    title: 'Merci',
    voice:
      'Vous connaissez maintenant les trois espaces NdamStore. Retrouvez cette vidéo et le guide écrit sur la page Guide. Bonne réussite à votre commerce !',
  },
];

function findFfmpeg() {
  const which = spawnSync('where.exe', ['ffmpeg'], { encoding: 'utf8' });
  if (which.status === 0) return which.stdout.split(/\r?\n/).find(Boolean);
  const p = path.join(process.env.LOCALAPPDATA || '', 'Microsoft', 'WinGet', 'Links', 'ffmpeg.exe');
  return fs.existsSync(p) ? p : null;
}

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms));
}

function speakToWav(text, wavPath) {
  const ps = `
Add-Type -AssemblyName System.Speech
$s = New-Object System.Speech.Synthesis.SpeechSynthesizer
try { $s.SelectVoice('Microsoft Hortense Desktop') } catch {
  $s.SelectVoiceByHints([System.Speech.Synthesis.VoiceGender]::Female, [System.Speech.Synthesis.VoiceAge]::Adult, 0, [System.Globalization.CultureInfo]::new('fr-FR'))
}
$s.Rate = -1
$s.Volume = 100
$s.SetOutputToWaveFile(${JSON.stringify(wavPath)})
$s.Speak(${JSON.stringify(text)})
$s.Dispose()
`;
  const script = path.join(outDir, '_speak.ps1');
  fs.writeFileSync(script, ps, 'utf8');
  const r = spawnSync('powershell', ['-ExecutionPolicy', 'Bypass', '-File', script], { encoding: 'utf8' });
  if (r.status !== 0) throw new Error('TTS failed: ' + (r.stderr || r.stdout));
}

function wavDurationSec(wavPath) {
  const ffmpeg = findFfmpeg();
  const r = spawnSync(ffmpeg, ['-i', wavPath], { encoding: 'utf8' });
  const err = r.stderr || '';
  const m = err.match(/Duration:\s*(\d+):(\d+):(\d+\.\d+)/);
  if (!m) return 4;
  return Number(m[1]) * 3600 + Number(m[2]) * 60 + Number(m[3]);
}

function ffmpegRun(ffmpeg, args) {
  const r = spawnSync(ffmpeg, args, { encoding: 'utf8' });
  if (r.status !== 0) {
    console.error((r.stderr || '').slice(-2000));
    throw new Error('ffmpeg fail');
  }
}

function makeTitlePng(outFile, kicker, title) {
  const php = `
$w=1280;$h=720;
$im=imagecreatetruecolor($w,$h);
for($y=0;$y<$h;$y++){
  $t=$y/($h-1);
  $col=imagecolorallocate($im,(int)(8+(18-8)*$t),(int)(55+(110-55)*$t),(int)(48+(95-48)*$t));
  imageline($im,0,$y,$w,$y,$col);
}
$white=imagecolorallocate($im,244,250,247);
$muted=imagecolorallocate($im,186,220,210);
$accent=imagecolorallocatealpha($im,255,255,255,108);
imagefilledellipse($im,1180,80,420,420,$accent);
imagefilledellipse($im,-60,700,360,360,$accent);
$font='C:/Windows/Fonts/segoeuib.ttf'; $fontR='C:/Windows/Fonts/segoeui.ttf';
if(!is_file($font)) $font='C:/Windows/Fonts/arialbd.ttf';
if(!is_file($fontR)) $fontR='C:/Windows/Fonts/arial.ttf';
imagettftext($im,16,0,72,110,$muted,$fontR,${JSON.stringify(kicker)});
imagettftext($im,46,0,72,250,$white,$font,${JSON.stringify(title)});
imagettftext($im,18,0,72,640,$muted,$font,'NdamStore');
imagettftext($im,14,0,72,672,$muted,$fontR,'La réussite de votre commerce.');
imagepng($im,${JSON.stringify(outFile)});
`;
  const f = path.join(outDir, '_title.php');
  fs.writeFileSync(f, `<?php\n${php}`);
  spawnSync('php', [f], { stdio: 'inherit' });
}

async function preparePage(page) {
  await page.addStyleTag({
    content: `
      .sf-toolbar, .sf-minitoolbar, #sfwdt*, [id^="sfwdt"] { display:none !important; }
      .sidebar-backdrop { display:none !important; }
      .app-shell { display: flex !important; min-height: 100vh !important; }
      .sidebar {
        position: fixed !important;
        left: 0 !important;
        top: 0 !important;
        width: 272px !important;
        max-width: 272px !important;
        height: 100vh !important;
        height: 100dvh !important;
        overflow: hidden !important;
        z-index: 1040 !important;
        transform: none !important;
        display: flex !important;
        flex-direction: column !important;
      }
      .brand-logo-sidebar {
        width: 100% !important;
        max-width: 11.5rem !important;
        max-height: 3.6rem !important;
        height: auto !important;
      }
      .content-wrap {
        margin-left: 272px !important;
        width: calc(100% - 272px) !important;
        min-height: 100vh !important;
        max-width: none !important;
      }
      .mobile-toggle { display: none !important; }
    `,
  });
  await page.evaluate(() => {
    document.documentElement.setAttribute('data-theme', 'light');
    document.documentElement.setAttribute('data-bs-theme', 'light');
    document.querySelector('.sidebar')?.classList.remove('open');
    document.querySelector('.sidebar-backdrop')?.classList.remove('show');
    window.scrollTo(0, 0);
  });
  await sleep(400);
}

async function login(page, email, password) {
  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle', timeout: 60000 });
  await preparePage(page);
  await page.fill('input[name="_username"]', email);
  await page.fill('input[name="_password"]', password);
  await Promise.all([
    page.waitForURL((u) => !String(u).includes('/login'), { timeout: 30000 }),
    page.click('button[type="submit"]'),
  ]);
  await page.waitForLoadState('networkidle');
  await preparePage(page);
}

async function logout(page) {
  const form = page.locator('form.sidebar-logout-form').first();
  if (await form.count()) {
    await form.evaluate((f) => f.submit());
    await page.waitForURL(/login|\/$/, { timeout: 20000 }).catch(() => {});
    await sleep(500);
  }
}

async function captureScene(page, scene, index) {
  const url = `${BASE}${scene.path}`;
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
  await preparePage(page);
  // laisser le layout / graphiques se poser
  await sleep(900);
  await page.evaluate(() => window.scrollTo(0, 0));
  const shot = path.join(outDir, 'shots', `${String(index).padStart(2, '0')}.png`);
  await page.screenshot({ path: shot, fullPage: false, type: 'png' });
  return shot;
}

async function main() {
  fs.rmSync(outDir, { recursive: true, force: true });
  fs.mkdirSync(path.join(outDir, 'shots'), { recursive: true });
  fs.mkdirSync(path.join(outDir, 'audio'), { recursive: true });
  fs.mkdirSync(path.join(outDir, 'segs'), { recursive: true });
  fs.mkdirSync(path.join(outDir, 'titles'), { recursive: true });

  const ffmpeg = findFfmpeg();
  if (!ffmpeg) throw new Error('ffmpeg manquant');

  console.log(`BASE=${BASE}`);
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    deviceScaleFactor: 1,
    locale: 'fr-FR',
    colorScheme: 'light',
  });
  const page = await context.newPage();

  let currentRole = null;
  const shotFiles = [];

  for (let i = 0; i < scenes.length; i++) {
    const scene = scenes[i];
    console.log(`\n[${i + 1}/${scenes.length}] ${scene.title}`);

    if (['admin', 'merchant', 'seller'].includes(scene.role) && scene.role !== currentRole) {
      if (currentRole) await logout(page);
      await login(page, accounts[scene.role].email, accounts[scene.role].password);
      currentRole = scene.role;
      console.log(`  connecté (${scene.role})`);
    }

    if (scene.role === 'outro' && currentRole) {
      await logout(page);
      currentRole = null;
    }

    const shotPath = path.join(outDir, 'shots', `${String(i + 1).padStart(2, '0')}.png`);
    if (process.env.SKIP_EXISTING_SHOTS === '1' && fs.existsSync(shotPath)) {
      console.log('  shot existante — skip');
      shotFiles.push({ scene, shot: shotPath, index: i + 1 });
      continue;
    }

    const shot = await captureScene(page, scene, i + 1);
    shotFiles.push({ scene, shot, index: i + 1 });
    console.log(`  capture OK`);
  }

  await context.close();
  await browser.close();

  // Cartons de section
  makeTitlePng(path.join(outDir, 'titles', 'intro.png'), 'GUIDE VIDÉO · FRANÇAIS', 'NdamStore');
  makeTitlePng(path.join(outDir, 'titles', 'admin.png'), 'ESPACE 1 / 3', 'Administrateur');
  makeTitlePng(path.join(outDir, 'titles', 'merchant.png'), 'ESPACE 2 / 3', 'Entrepreneur');
  makeTitlePng(path.join(outDir, 'titles', 'seller.png'), 'ESPACE 3 / 3', 'Vendeur');
  makeTitlePng(path.join(outDir, 'titles', 'outro.png'), 'MERCI', 'Bonne réussite');

  const concatParts = [];
  let segN = 0;

  const pushStill = (png, wav, label) => {
    segN += 1;
    const dur = Math.max(2.8, wavDurationSec(wav) + 0.35);
    const out = path.join(outDir, 'segs', `seg-${String(segN).padStart(3, '0')}.mp4`);
    // léger zoom moderne (Ken Burns soft)
    ffmpegRun(ffmpeg, [
      '-y',
      '-loop', '1', '-i', png,
      '-i', wav,
      '-filter_complex',
      `[0:v]scale=1600:1000:force_original_aspect_ratio=increase,crop=1440:900,scale=1280:720,zoompan=z='min(1.08,1+0.00035*on)':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':d=${Math.ceil(dur * 30)}:s=1280x720:fps=30,format=yuv420p[v]`,
      '-map', '[v]', '-map', '1:a',
      '-c:v', 'libx264', '-tune', 'stillimage', '-c:a', 'aac', '-b:a', '192k',
      '-t', String(dur),
      out,
    ]);
    concatParts.push(out);
    console.log(`  seg ${label} (${dur.toFixed(1)}s)`);
  };

  const pushTitle = (png, text) => {
    const wav = path.join(outDir, 'audio', `title-${segN + 1}.wav`);
    speakToWav(text, wav);
    pushStill(png, wav, path.basename(png));
  };

  console.log('\n=== Voix + assemblage ===');
  pushTitle(path.join(outDir, 'titles', 'intro.png'), 'Bienvenue sur NdamStore, démonstration des trois espaces.');

  let lastRole = null;
  for (const item of shotFiles) {
    if (item.scene.role === 'admin' && lastRole !== 'admin') {
      pushTitle(path.join(outDir, 'titles', 'admin.png'), 'Espace administrateur.');
      lastRole = 'admin';
    }
    if (item.scene.role === 'merchant' && lastRole !== 'merchant') {
      pushTitle(path.join(outDir, 'titles', 'merchant.png'), 'Espace entrepreneur.');
      lastRole = 'merchant';
    }
    if (item.scene.role === 'seller' && lastRole !== 'seller') {
      pushTitle(path.join(outDir, 'titles', 'seller.png'), 'Espace vendeur.');
      lastRole = 'seller';
    }

    const wav = path.join(outDir, 'audio', `n-${String(item.index).padStart(2, '0')}.wav`);
    speakToWav(item.scene.voice, wav);
    pushStill(item.shot, wav, item.scene.title);
  }

  pushTitle(path.join(outDir, 'titles', 'outro.png'), 'Merci. Retrouvez le guide sur NdamStore. Bonne réussite !');

  const listFile = path.join(outDir, 'concat.txt');
  fs.writeFileSync(listFile, concatParts.map((p) => `file '${p.replace(/\\/g, '/')}'`).join('\n'));
  fs.mkdirSync(path.dirname(finalMp4), { recursive: true });
  ffmpegRun(ffmpeg, [
    '-y', '-f', 'concat', '-safe', '0', '-i', listFile,
    '-c:v', 'libx264', '-c:a', 'aac', '-pix_fmt', 'yuv420p', '-movflags', '+faststart',
    finalMp4,
  ]);

  // poster
  const posterSrc = shotFiles.find((s) => s.scene.path === '/dashboard' && s.scene.role === 'merchant')?.shot
    || shotFiles[2]?.shot;
  if (posterSrc) {
    spawnSync('powershell', [
      '-Command',
      `Add-Type -AssemblyName System.Drawing; $i=[System.Drawing.Image]::FromFile('${posterSrc}'); $i.Save('${posterJpg}', [System.Drawing.Imaging.ImageFormat]::Jpeg); $i.Dispose()`,
    ]);
  }

  const size = fs.statSync(finalMp4).size;
  console.log(`\nVIDEO OK ${finalMp4}`);
  console.log(`Taille ${(size / 1024 / 1024).toFixed(1)} Mo`);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});

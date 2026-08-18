<?php

namespace App\Service;

class PptxGeneratorService
{
    private const W = 12192000;
    private const H = 6858000;
    private const BRAND    = '0C5C50';
    private const BRAND_LT = '147A6A';
    private const ACCENT   = '2A9B88';
    private const LIME     = 'A8D5BA';
    private const CREAM    = 'F0F7F4';
    private const WHITE    = 'FFFFFF';
    private const INK      = '13201C';
    private const MUTED    = '4A6560';
    private const SURFACE  = 'E8F3EF';

    public function generate(string $appName, string $tagline, array $platform): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'pptx');

        try {
            $z = new \ZipArchive();
            $res = $z->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            if ($res !== true) {
                return '';
            }

            $slides = $this->buildSlides($appName, $tagline, $platform);
            $n = count($slides);

            $this->writeBase($z, $appName);
            $this->writeTheme($z);

            for ($i = 0; $i < $n; $i++) {
                $num = $i + 1;
                $z->addFromString("ppt/slides/slide{$num}.xml", $this->renderSlide($slides[$i]));
                $z->addFromString("ppt/slides/_rels/slide{$num}.xml.rels", $this->wrapRels(
                    '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/>'
                ));
            }

            $z->addFromString('[Content_Types].xml', $this->contentTypes($n));
            $z->addFromString('ppt/presentation.xml', $this->presentationXml($n));

            $relXml = '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="slideMasters/slideMaster1.xml"/>';
            $relXml .= '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="theme/theme1.xml"/>';
            for ($i = 0; $i < $n; $i++) {
                $relXml .= '<Relationship Id="rId'.($i + 3).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide'.($i + 1).'.xml"/>';
            }
            $z->addFromString('ppt/_rels/presentation.xml.rels', $this->wrapRels($relXml));

            $z->close();

            $data = file_get_contents($tmp);

            return $data ?: '';
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }

    private function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_NOQUOTES, 'UTF-8');
    }

    // ── Base OOXML files ───────────────────────────────────────────

    private function writeBase(\ZipArchive $z, string $app): void
    {
        $z->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>');

        $z->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <dc:title>'.$this->esc($app).'</dc:title>
  <dc:creator>'.$this->esc($app).'</dc:creator>
</cp:coreProperties>');

        $z->addFromString('docProps/app.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">
  <Application>'.$this->esc($app).'</Application>
</Properties>');
    }

    private function writeTheme(\ZipArchive $z): void
    {
        $z->addFromString('ppt/theme/theme1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="NdamStore">
  <a:themeElements>
    <a:clrScheme name="NdamStore">
      <a:dk1><a:srgbClr val="'.$this::INK.'"/></a:dk1>
      <a:lt1><a:srgbClr val="'.$this::WHITE.'"/></a:lt1>
      <a:dk2><a:srgbClr val="'.$this::BRAND.'"/></a:dk2>
      <a:lt2><a:srgbClr val="'.$this::CREAM.'"/></a:lt2>
      <a:accent1><a:srgbClr val="'.$this::BRAND.'"/></a:accent1>
      <a:accent2><a:srgbClr val="'.$this::BRAND_LT.'"/></a:accent2>
      <a:accent3><a:srgbClr val="'.$this::ACCENT.'"/></a:accent3>
      <a:accent4><a:srgbClr val="'.$this::LIME.'"/></a:accent4>
      <a:accent5><a:srgbClr val="0A3F37"/></a:accent5>
      <a:accent6><a:srgbClr val="1AA58C"/></a:accent6>
      <a:hlink><a:srgbClr val="'.$this::BRAND_LT.'"/></a:hlink>
      <a:folHlink><a:srgbClr val="'.$this::BRAND.'"/></a:folHlink>
    </a:clrScheme>
    <a:fontScheme name="NdamStore">
      <a:majorFont><a:latin typeface="Calibri"/><a:ea typeface=""/><a:cs typeface=""/></a:majorFont>
      <a:minorFont><a:latin typeface="Calibri"/><a:ea typeface=""/><a:cs typeface=""/></a:minorFont>
    </a:fontScheme>
    <a:fmtScheme name="Office">
      <a:fillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:fillStyleLst>
      <a:lnStyleLst><a:ln w="6350"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:ln><a:ln w="6350"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:ln><a:ln w="6350"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:ln></a:lnStyleLst>
      <a:effectStyleLst><a:effectStyle><a:effectLst/></a:effectStyle><a:effectStyle><a:effectLst/></a:effectStyle><a:effectStyle><a:effectLst/></a:effectStyle></a:effectStyleLst>
      <a:bgFillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:bgFillStyleLst>
    </a:fmtScheme>
  </a:themeElements>
</a:theme>');

        $z->addFromString('ppt/slideLayouts/slideLayout1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<p:sldLayout xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
  xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" type="blank" preserve="1">
  <p:cSld name="Blank">
    <p:spTree>
      <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
      <p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr>
    </p:spTree>
  </p:cSld>
</p:sldLayout>');

        $z->addFromString('ppt/slideLayouts/_rels/slideLayout1.xml.rels', $this->wrapRels(
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="../slideMasters/slideMaster1.xml"/>'
        ));

        $z->addFromString('ppt/slideMasters/slideMaster1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<p:sldMaster xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
  xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">
  <p:cSld name="Master">
    <p:spTree>
      <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>
      <p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr>
    </p:spTree>
  </p:cSld>
  <p:clrMap bg1="lt1" tx1="dk1" bg2="lt2" tx2="dk2" accent1="accent1" accent2="accent2" accent3="accent3" accent4="accent4" accent5="accent5" accent6="accent6" hlink="hlink" folHlink="folHlink"/>
  <p:sldLayoutIdLst><p:sldLayoutId id="2147483649" r:id="rId1"/></p:sldLayoutIdLst>
</p:sldMaster>');

        $z->addFromString('ppt/slideMasters/_rels/slideMaster1.xml.rels', $this->wrapRels(
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/>'
        ));
    }

    // ── Slide data ─────────────────────────────────────────────────

    private function buildSlides(string $app, string $tagline, array $p): array
    {
        return [
            ['cover', $app, $tagline,
                'Présentation complète de la plateforme',
                $p['city'].', '.$p['country'].' — '.date('Y')],

            ['sommaire', 'Sommaire', [
                ['01', 'Vision & idée générale'],
                ['02', 'Fonctionnalités détaillées'],
                ['03', 'Apport à la communauté'],
                ['04', 'Architecture technique'],
                ['05', 'Feuille de route & avenir'],
            ]],

            ['bullets', 'Vision & idée générale', [
                "Plateforme SaaS de gestion pour entrepreneurs ouest-africains",
                "Démocratiser l'accès à des outils de gestion professionnels",
                "Du registre papier à la gestion numérique centralisée",
                "Multi-entreprises, multi-utilisateurs",
                "Conçue pour les réalités du commerce local",
            ], true],

            ['cards3', 'Fonctionnalités clés', 'Point de vente & ventes', [
                'Interface de caisse intuitive avec recherche rapide',
                'Calcul automatique de la TVA, remises et ristournes',
                'Modes de paiement multiples',
            ], 'Produits & stock', [
                'Catalogue avec images et codes-barres',
                'Suivi des mouvements en temps réel',
                'Alertes de stock faible',
            ], 'Clients & fiscalité', [
                'Fiches clients avec historique',
                'Configuration TVA et NINEA',
                'Rapports fiscaux prêts',
            ]],

            ['cards3', 'Gestion avancée', 'Finance & caisse', [
                'Ouverture/fermeture avec contrôle',
                'Rapprochement automatique',
                'Rapports de flux financiers',
            ], 'Bénéfices & marges', [
                'Suivi par produit et catégorie',
                'Analyse de rentabilité',
                'Tableaux de bord Chart.js',
            ], 'Équipe & accès', [
                'Rôles et permissions granulaires',
                'Journal d\'activité par utilisateur',
                'Gestion multi-utilisateurs',
            ]],

            ['impact', 'Apport à la communauté', [
                ['+40%', 'Productivité entrepreneurs(se)'],
                ['-60%', 'Temps comptabilité'],
                ['100%', 'Conformité fiscale'],
                ['24/7', 'Accès aux données'],
            ], [
                'Modernisation du commerce local',
                'Transition numérique inclusive',
                'Création d\'emplois tech',
                'Rayonnement régional',
            ]],

            ['tech', 'Architecture technique', [
                'Serveur', ['Symfony PHP 8.4+', 'Doctrine ORM', 'PostgreSQL', 'Multi-tenant'],
                'Frontend', ['Bootstrap 5.3', 'JS vanilla ES6+', 'Chart.js', 'PWA'],
                'Sécurité', ['Auth sécurisée', 'RBAC', 'Chiffrement', 'Audit trail'],
            ]],

            ['timeline', 'Feuille de route', [
                ['Phase 1 — Core productif', 'done'],
                ['Phase 2 — Fiscalité & conformité', 'done'],
                ['Phase 3 — Expansion & mobile money', 'progress'],
                ['Phase 4 — Intelligence artificielle', 'planned'],
                ['Phase 5 — Marketplace & multi-pays', 'planned'],
            ]],

            ['end', 'Merci', 'Prêt à commencer ?',
                $p['email'].' · '.$p['phone'],
                $app.' — '.$tagline],
        ];
    }

    // ── Slide rendering ────────────────────────────────────────────

    private function renderSlide(array $s): string
    {
        return match ($s[0]) {
            'cover'     => $this->renderCover($s),
            'sommaire'  => $this->renderSommaire($s),
            'bullets'   => $this->renderBullets($s),
            'cards3'    => $this->renderCards3($s),
            'impact'    => $this->renderImpact($s),
            'tech'      => $this->renderTech($s),
            'timeline'  => $this->renderTimeline($s),
            'end'       => $this->renderEnd($s),
            default     => $this->renderBullets($s),
        };
    }

    // ── Cover slide: gradient bg + big title ───────────────────────

    private function renderCover(array $s): string
    {
        $sp = '';
        // Gradient background: full-slide brand
        $sp .= '<p:bg><p:bgRef idx="1001"><a:solidFill><a:srgbClr val="'.$this::BRAND.'"/></a:solidFill></p:bgRef></p:bg>';
        // Decorative circle top-right
        $sp .= $this->circle(100, 9200000, -400000, 4000000, $this::BRAND_LT, 25);
        // Decorative circle bottom-left
        $sp .= $this->circle(101, -600000, 4800000, 3200000, '0A3F37', 20);
        // Accent bar at left
        $sp .= $this->rect(102, 800000, 2000000, 120000, 2400000, $this::ACCENT);
        // Title
        $sp .= $this->tp(2, $s[1], 1200000, 2200000, 9800000, 1100000, 5600, $this::WHITE, true);
        // Tagline
        if (!empty($s[2])) {
            $sp .= $this->tp(3, $s[2], 1200000, 3400000, 9800000, 700000, 2800, $this::CREAM, false);
        }
        // Subtitle
        if (!empty($s[3])) {
            $sp .= $this->tp(4, $s[3], 1200000, 4200000, 9800000, 500000, 1800, $this::LIME, false);
        }
        // Location
        if (!empty($s[4])) {
            $sp .= $this->tp(5, $s[4], 1200000, 5600000, 9800000, 400000, 1500, $this::LIME, false);
        }

        return $this->wrapSlide($sp);
    }

    // ── Sommaire: numbered list with accent ────────────────────────

    private function renderSommaire(array $s): string
    {
        $sp = $this->whiteBg();
        $sp .= $this->leftAccentBar(1);
        $sp .= $this->tp(2, $s[1], 1100000, 600000, 10000000, 800000, 3800, $this::BRAND, true);

        foreach ($s[2] as $i => $item) {
            $num = $item[0];
            $txt = $item[1];
            $y = 1800000 + ($i * 850000);
            // Number badge
            $sp .= $this->numBadge(20 + $i, $num, 1100000, $y, 600000, 600000);
            // Text
            $sp .= $this->tp(30 + $i, $txt, 1900000, $y + 80000, 9200000, 500000, 2200, $this::INK, false);
        }

        return $this->wrapSlide($sp);
    }

    // ── Bullets with accent dot ────────────────────────────────────

    private function renderBullets(array $s): string
    {
        $title = $s[1] ?? '';
        $items = $s[2] ?? [];
        $hasAccent = $s[3] ?? false;

        $sp = $this->whiteBg();
        $sp .= $this->leftAccentBar(1);
        $sp .= $this->tp(2, $title, 1100000, 600000, 10000000, 800000, 3800, $this::BRAND, true);
        $sp .= $this->accentLine(3, 1100000, 1450000, 2200000);

        foreach ($items as $i => $item) {
            $y = 1750000 + ($i * 600000);
            $sp .= $this->bulletDot(10 + $i, 1100000, $y + 110000);
            $sp .= $this->tp(20 + $i, $item, 1450000, $y, 9650000, 500000, 2000, $this::INK, false);
        }

        return $this->wrapSlide($sp);
    }

    // ── 3-column cards ─────────────────────────────────────────────

    private function renderCards3(array $s): string
    {
        $sp = $this->whiteBg();
        $sp .= $this->leftAccentBar(1);
        $sp .= $this->tp(2, $s[1], 1100000, 600000, 10000000, 800000, 3800, $this::BRAND, true);

        $cols = [
            ['x' => 700000,  'h' => $s[2], 'items' => $s[3]],
            ['x' => 4200000, 'h' => $s[4], 'items' => $s[5]],
            ['x' => 7700000, 'h' => $s[6], 'items' => $s[7]],
        ];

        $id = 10;
        foreach ($cols as $col) {
            $x = $col['x'];
            // Card background
            $sp .= $this->cardBg($id++, $x, 1600000, 3400000, 4000000);
            // Card title
            $sp .= $this->tp($id++, $col['h'], $x + 200000, 1750000, 3000000, 400000, 2000, $this::BRAND, true);
            // Accent line
            $sp .= $this->accentLine($id++, $x + 200000, 2200000, 800000);
            // Items
            foreach ($col['items'] as $i => $item) {
                $y = 2450000 + ($i * 550000);
                $sp .= $this->bulletDot($id++, $x + 200000, $y + 110000);
                $sp .= $this->tp($id++, $item, $x + 500000, $y, 2700000, 420000, 1700, $this::INK, false);
            }
        }

        return $this->wrapSlide($sp);
    }

    // ── Impact stats ───────────────────────────────────────────────

    private function renderImpact(array $s): string
    {
        $sp = $this->whiteBg();
        $sp .= $this->leftAccentBar(1);
        $sp .= $this->tp(2, $s[1], 1100000, 600000, 10000000, 800000, 3800, $this::BRAND, true);

        // Stats row
        foreach ($s[2] as $i => $stat) {
            $x = 700000 + ($i * 2750000);
            // Stat card bg
            $sp .= $this->statCard(20 + $i, $x, 1700000, 2400000, 1600000);
            // Big number
            $sp .= $this->tp(40 + $i, $stat[0], $x + 150000, 1850000, 2100000, 800000, 4200, $this::BRAND, true);
            // Label
            $sp .= $this->tp(60 + $i, $stat[1], $x + 150000, 2700000, 2100000, 450000, 1400, $this::MUTED, false);
        }

        // Bottom impacts list
        $sp .= $this->accentLine(80, 1100000, 3600000, 1800000);
        foreach ($s[3] as $i => $item) {
            $x = 1100000 + ($i * 2500000);
            $sp .= $this->bulletDot(85 + $i, $x, 3900000 + 110000);
            $sp .= $this->tp(90 + $i, $item, $x + 350000, 3900000, 2200000, 350000, 1600, $this::INK, false);
        }

        return $this->wrapSlide($sp);
    }

    // ── Tech columns ───────────────────────────────────────────────

    private function renderTech(array $s): string
    {
        $sp = $this->whiteBg();
        $sp .= $this->leftAccentBar(1);
        $sp .= $this->tp(2, $s[1], 1100000, 600000, 10000000, 800000, 3800, $this::BRAND, true);

        $cols = $s[2];
        $colX = [800000, 4200000, 7600000];
        $icons = ['&#9881;', '&#9741;', '&#9883;'];

        for ($c = 0; $c < 3; $c++) {
            $x = $colX[$c];
            $catLabel = $cols[$c * 2];
            $catItems = $cols[$c * 2 + 1];
            // Card bg
            $sp .= $this->cardBg(10 + $c, $x, 1600000, 3300000, 3800000);
            // Category title
            $sp .= $this->tp(20 + $c, $catLabel, $x + 200000, 1750000, 2900000, 400000, 2200, $this::BRAND, true);
            $sp .= $this->accentLine(30 + $c, $x + 200000, 2200000, 600000);
            // Items
            foreach ($catItems as $i => $item) {
                $y = 2450000 + ($i * 550000);
                $sp .= $this->bulletDot(40 + ($c * 10) + $i, $x + 200000, $y + 110000);
                $sp .= $this->tp(50 + ($c * 10) + $i, $item, $x + 500000, $y, 2600000, 420000, 1700, $this::INK, false);
            }
        }

        return $this->wrapSlide($sp);
    }

    // ── Timeline ───────────────────────────────────────────────────

    private function renderTimeline(array $s): string
    {
        $sp = $this->whiteBg();
        $sp .= $this->leftAccentBar(1);
        $sp .= $this->tp(2, $s[1], 1100000, 600000, 10000000, 800000, 3800, $this::BRAND, true);

        // Vertical line
        $sp .= $this->rect(100, 1500000, 1700000, 40000, 3800000, $this::LIME);

        foreach ($s[2] as $i => $phase) {
            $y = 1700000 + ($i * 780000);
            $status = $phase[1];
            $label = $phase[0];

            // Dot
            $dotColor = match ($status) {
                'done'     => $this::BRAND,
                'progress' => $this::ACCENT,
                default    => $this::LIME,
            };
            $sp .= $this->circle(20 + $i, 1420000, $y + 50000, 200000, $dotColor, 100);

            // Status pill
            $pillColor = match ($status) {
                'done'     => $this::BRAND,
                'progress' => $this::ACCENT,
                default    => $this::MUTED,
            };
            $pillText = match ($status) {
                'done'     => 'En production',
                'progress' => 'En cours',
                default    => 'Planifié',
            };
            $sp .= $this->pill(30 + $i, $pillText, 1800000, $y, $pillColor);
            // Label
            $sp .= $this->tp(40 + $i, $label, 3200000, $y, 8000000, 500000, 1800, $this::INK, false);
        }

        return $this->wrapSlide($sp);
    }

    // ── End slide ──────────────────────────────────────────────────

    private function renderEnd(array $s): string
    {
        $sp = '';
        $sp .= '<p:bg><p:bgRef idx="1001"><a:solidFill><a:srgbClr val="'.$this::BRAND.'"/></a:solidFill></p:bgRef></p:bg>';
        $sp .= $this->circle(100, 8800000, -200000, 3600000, $this::BRAND_LT, 22);
        $sp .= $this->circle(101, -400000, 4600000, 2800000, '0A3F37', 18);
        // Accent bar
        $sp .= $this->rect(102, 5200000, 2800000, 200000, 1800000, $this::ACCENT);
        // Title
        $sp .= $this->tp(2, $s[1], 1200000, 2000000, 9800000, 1000000, 5200, $this::WHITE, true);
        // Tagline
        if (!empty($s[2])) {
            $sp .= $this->tp(3, $s[2], 1200000, 3100000, 9800000, 600000, 2600, $this::CREAM, false);
        }
        // Contact
        if (!empty($s[3])) {
            $sp .= $this->tp(4, $s[3], 1200000, 4000000, 9800000, 400000, 1600, $this::LIME, false);
        }
        // App name
        if (!empty($s[4])) {
            $sp .= $this->tp(5, $s[4], 1200000, 5200000, 9800000, 400000, 1400, $this::LIME, false);
        }

        return $this->wrapSlide($sp);
    }

    // ── Shape primitives ───────────────────────────────────────────

    /** Title paragraph */
    private function tp(int $id, string $raw, int $x, int $y, int $cx, int $cy, int $sz, string $clr, bool $bold): string
    {
        $text = $this->esc($raw);
        $b = $bold ? '<a:b/>' : '';

        return '<p:sp>
  <p:nvSpPr><p:cNvPr id="'.$id.'" name="T'.$id.'"/><p:cNvSpPr txBox="1"/><p:nvPr/></p:nvSpPr>
  <p:spPr>
    <a:xfrm><a:off x="'.$x.'" y="'.$y.'"/><a:ext cx="'.$cx.'" cy="'.$cy.'"/></a:xfrm>
    <a:prstGeom prst="rect"><a:avLst/></a:prstGeom>
    <a:noFill/>
  </p:spPr>
  <p:txBody>
    <a:bodyPr wrap="square" rtlCol="0" anchor="t" lIns="91440" tIns="45720" rIns="91440" bIns="45720"/>
    <a:lstStyle/>
    <a:p><a:pPr algn="l"/>
      <a:r><a:rPr lang="fr-FR" altLang="en-US" sz="'.$sz.'">'.$b.'
        <a:solidFill><a:srgbClr val="'.$clr.'"/></a:solidFill>
        <a:latin typeface="Calibri"/><a:ea typeface=""/><a:cs typeface=""/>
      </a:rPr><a:t>'.$text.'</a:t></a:r>
    </a:p>
  </p:txBody>
</p:sp>';
    }

    /** Bullet dot (small circle) */
    private function bulletDot(int $id, int $x, int $y): string
    {
        return '<p:sp>
  <p:nvSpPr><p:cNvPr id="'.$id.'" name="Dot'.$id.'"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
  <p:spPr>
    <a:xfrm><a:off x="'.$x.'" y="'.$y.'"/><a:ext cx="110000" cy="110000"/></a:xfrm>
    <a:prstGeom prst="ellipse"><a:avLst/></a:prstGeom>
    <a:solidFill><a:srgbClr val="'.$this::ACCENT.'"/></a:solidFill>
  </p:spPr>
  <p:txBody><a:bodyPr/><a:p><a:endParaRPr lang="fr-FR"/></a:p></p:txBody>
</p:sp>';
    }

    /** Number badge (rounded rect) */
    private function numBadge(int $id, string $num, int $x, int $y, int $cx, int $cy): string
    {
        return '<p:sp>
  <p:nvSpPr><p:cNvPr id="'.$id.'" name="Badge'.$id.'"/><p:cNvSpPr txBox="1"/><p:nvPr/></p:nvSpPr>
  <p:spPr>
    <a:xfrm><a:off x="'.$x.'" y="'.$y.'"/><a:ext cx="'.$cx.'" cy="'.$cy.'"/></a:xfrm>
    <a:prstGeom prst="roundRect"><a:avLst><a:gd name="adj" fmla="val 25000"/></a:avLst></a:prstGeom>
    <a:solidFill><a:srgbClr val="'.$this::BRAND.'"/></a:solidFill>
  </p:spPr>
  <p:txBody>
    <a:bodyPr wrap="square" rtlCol="0" anchor="ctr" lIns="45720" tIns="45720" rIns="45720" bIns="45720"/>
    <a:lstStyle/>
    <a:p><a:pPr algn="ctr"/>
      <a:r><a:rPr lang="fr-FR" altLang="en-US" sz="2200"><a:b/>
        <a:solidFill><a:srgbClr val="'.$this::WHITE.'"/></a:solidFill>
        <a:latin typeface="Calibri"/><a:ea typeface=""/><a:cs typeface=""/>
      </a:rPr><a:t>'.$this->esc($num).'</a:t></a:r>
    </a:p>
  </p:txBody>
</p:sp>';
    }

    /** Pill (small rounded rect with text) */
    private function pill(int $id, string $txt, int $x, int $y, string $clr): string
    {
        $text = $this->esc($txt);

        return '<p:sp>
  <p:nvSpPr><p:cNvPr id="'.$id.'" name="Pill'.$id.'"/><p:cNvSpPr txBox="1"/><p:nvPr/></p:nvSpPr>
  <p:spPr>
    <a:xfrm><a:off x="'.$x.'" y="'.$y.'"/><a:ext cx="1300000" cy="350000"/></a:xfrm>
    <a:prstGeom prst="roundRect"><a:avLst><a:gd name="adj" fmla="val 50000"/></a:avLst></a:prstGeom>
    <a:solidFill><a:srgbClr val="'.$clr.'"><a:alpha val="20000"/></a:srgbClr></a:solidFill>
    <a:ln w="0"><a:noFill/></a:ln>
  </p:spPr>
  <p:txBody>
    <a:bodyPr wrap="square" rtlCol="0" anchor="ctr" lIns="91440" tIns="22860" rIns="91440" bIns="22860"/>
    <a:lstStyle/>
    <a:p><a:pPr algn="ctr"/>
      <a:r><a:rPr lang="fr-FR" altLang="en-US" sz="1200"><a:b/>
        <a:solidFill><a:srgbClr val="'.$clr.'"/></a:solidFill>
        <a:latin typeface="Calibri"/><a:ea typeface=""/><a:cs typeface=""/>
      </a:rPr><a:t>'.$text.'</a:t></a:r>
    </a:p>
  </p:txBody>
</p:sp>';
    }

    /** Card background (rounded rect) */
    private function cardBg(int $id, int $x, int $y, int $cx, int $cy): string
    {
        return '<p:sp>
  <p:nvSpPr><p:cNvPr id="'.$id.'" name="Card'.$id.'"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
  <p:spPr>
    <a:xfrm><a:off x="'.$x.'" y="'.$y.'"/><a:ext cx="'.$cx.'" cy="'.$cy.'"/></a:xfrm>
    <a:prstGeom prst="roundRect"><a:avLst><a:gd name="adj" fmla="val 4000"/></a:avLst></a:prstGeom>
    <a:solidFill><a:srgbClr val="'.$this::CREAM.'"/></a:solidFill>
    <a:ln w="12700"><a:solidFill><a:srgbClr val="'.$this::LIME.'"/></a:solidFill></a:ln>
  </p:spPr>
  <p:txBody><a:bodyPr/><a:p><a:endParaRPr lang="fr-FR"/></a:p></p:txBody>
</p:sp>';
    }

    /** Stat card background */
    private function statCard(int $id, int $x, int $y, int $cx, int $cy): string
    {
        return '<p:sp>
  <p:nvSpPr><p:cNvPr id="'.$id.'" name="Stat'.$id.'"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
  <p:spPr>
    <a:xfrm><a:off x="'.$x.'" y="'.$y.'"/><a:ext cx="'.$cx.'" cy="'.$cy.'"/></a:xfrm>
    <a:prstGeom prst="roundRect"><a:avLst><a:gd name="adj" fmla="val 6000"/></a:avLst></a:prstGeom>
    <a:solidFill><a:srgbClr val="'.$this::CREAM.'"/></a:solidFill>
    <a:ln w="12700"><a:solidFill><a:srgbClr val="'.$this::LIME.'"/></a:solidFill></a:ln>
  </p:spPr>
  <p:txBody><a:bodyPr/><a:p><a:endParaRPr lang="fr-FR"/></a:p></p:txBody>
</p:sp>';
    }

    /** Decorative circle */
    private function circle(int $id, int $x, int $y, int $diam, string $clr, int $alphaPct): string
    {
        return '<p:sp>
  <p:nvSpPr><p:cNvPr id="'.$id.'" name="C'.$id.'"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
  <p:spPr>
    <a:xfrm><a:off x="'.$x.'" y="'.$y.'"/><a:ext cx="'.$diam.'" cy="'.$diam.'"/></a:xfrm>
    <a:prstGeom prst="ellipse"><a:avLst/></a:prstGeom>
    <a:solidFill><a:srgbClr val="'.$clr.'"><a:alpha val="'.($alphaPct * 1000).'"/></a:srgbClr></a:solidFill>
  </p:spPr>
  <p:txBody><a:bodyPr/><a:p><a:endParaRPr lang="fr-FR"/></a:p></p:txBody>
</p:sp>';
    }

    /** Horizontal accent line */
    private function accentLine(int $id, int $x, int $y, int $cx): string
    {
        return '<p:sp>
  <p:nvSpPr><p:cNvPr id="'.$id.'" name="Line'.$id.'"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
  <p:spPr>
    <a:xfrm><a:off x="'.$x.'" y="'.$y.'"/><a:ext cx="'.$cx.'" cy="36000"/></a:xfrm>
    <a:prstGeom prst="rect"><a:avLst/></a:prstGeom>
    <a:solidFill><a:srgbClr val="'.$this::ACCENT.'"/></a:solidFill>
  </p:spPr>
  <p:txBody><a:bodyPr/><a:p><a:endParaRPr lang="fr-FR"/></a:p></p:txBody>
</p:sp>';
    }

    /** Left accent bar (decorative) */
    private function leftAccentBar(int $id): string
    {
        return '<p:sp>
  <p:nvSpPr><p:cNvPr id="'.$id.'" name="Bar'.$id.'"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
  <p:spPr>
    <a:xfrm><a:off x="600000" y="600000"/><a:ext cx="80000" cy="5200000"/></a:xfrm>
    <a:prstGeom prst="roundRect"><a:avLst><a:gd name="adj" fmla="val 50000"/></a:avLst></a:prstGeom>
    <a:solidFill><a:srgbClr val="'.$this::BRAND.'"><a:alpha val="15000"/></a:srgbClr></a:solidFill>
  </p:spPr>
  <p:txBody><a:bodyPr/><a:p><a:endParaRPr lang="fr-FR"/></a:p></p:txBody>
</p:sp>';
    }

    /** White background */
    private function whiteBg(): string
    {
        return '<p:bg><p:bgRef idx="1001"><a:solidFill><a:srgbClr val="'.$this::WHITE.'"/></a:solidFill></p:bgRef></p:bg>';
    }

    /** Rect shape */
    private function rect(int $id, int $x, int $y, int $cx, int $cy, string $clr): string
    {
        return '<p:sp>
  <p:nvSpPr><p:cNvPr id="'.$id.'" name="R'.$id.'"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
  <p:spPr>
    <a:xfrm><a:off x="'.$x.'" y="'.$y.'"/><a:ext cx="'.$cx.'" cy="'.$cy.'"/></a:xfrm>
    <a:prstGeom prst="rect"><a:avLst/></a:prstGeom>
    <a:solidFill><a:srgbClr val="'.$clr.'"/></a:solidFill>
  </p:spPr>
  <p:txBody><a:bodyPr/><a:p><a:endParaRPr lang="fr-FR"/></a:p></p:txBody>
</p:sp>';
    }

    // ── Slide wrapper ──────────────────────────────────────────────

    private function wrapSlide(string $spTree): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'
            . ' xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">' . "\n"
            . '<p:cSld>' . "\n"
            . '<p:spTree>' . "\n"
            . '<p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>' . "\n"
            . '<p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr>' . "\n"
            . $spTree . "\n"
            . '</p:spTree>' . "\n"
            . '</p:cSld>' . "\n"
            . '</p:sld>';
    }

    // ── OOXML scaffolding ──────────────────────────────────────────

    private function contentTypes(int $n): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' . "\n";
        $xml .= '  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' . "\n";
        $xml .= '  <Default Extension="xml" ContentType="application/xml"/>' . "\n";
        $xml .= '  <Override PartName="/ppt/presentation.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml"/>' . "\n";
        $xml .= '  <Override PartName="/ppt/slideMasters/slideMaster1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideMaster+xml"/>' . "\n";
        $xml .= '  <Override PartName="/ppt/slideLayouts/slideLayout1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideLayout+xml"/>' . "\n";
        $xml .= '  <Override PartName="/ppt/theme/theme1.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>' . "\n";
        $xml .= '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>' . "\n";
        $xml .= '  <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>' . "\n";
        for ($i = 1; $i <= $n; $i++) {
            $xml .= '  <Override PartName="/ppt/slides/slide'.$i.'.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/>' . "\n";
        }
        $xml .= '</Types>';

        return $xml;
    }

    private function presentationXml(int $n): string
    {
        $ids = '';
        for ($i = 0; $i < $n; $i++) {
            $ids .= '<p:sldId id="'.(256 + $i).'" r:id="rId'.($i + 3).'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<p:presentation xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'
            . ' xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">' . "\n"
            . '<p:sldMasterIdLst><p:sldMasterId id="2147483648" r:id="rId1"/></p:sldMasterIdLst>' . "\n"
            . '<p:sldIdLst>'.$ids.'</p:sldIdLst>' . "\n"
            . '<p:sldSz cx="'.self::W.'" cy="'.self::H.'"/>' . "\n"
            . '<p:notesSz cx="'.self::H.'" cy="'.self::W.'"/>' . "\n"
            . '</p:presentation>';
    }

    private function wrapRels(string $inner): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . "\n"
            . $inner . "\n"
            . '</Relationships>';
    }
}

# Guide vidéo — démo réelle des 3 dashboards

## Fichier
- `public/videos/ndamstore-guide-nouveaux-utilisateurs.mp4`
- Visible sur `/guide#video`

## Contenu
1. **Admin** — /admin, utilisateurs, entrepreneurs, entreprises, contrats, abonnements, fiscalité, journal
2. **Entrepreneur** — dashboard, POS, ventes, produits, catégories, fournisseurs, clients, crédits, stock, achats, inventaires, caisse, dépenses, bénéfices, rapports, audit, fiscalité, shops, vendeurs, facturation, profil
3. **Vendeur** — dashboard, POS, ventes, produits, clients, caisse, profil

## Rebuild (local)
```powershell
# Terminal 1
php -S 127.0.0.1:8091 -t public public/index.php

# Terminal 2
cd tools/demo-record
npm install
npx playwright install chromium
$env:BASE_URL='http://127.0.0.1:8091'
node record.mjs
```

Comptes fixtures : `admin@boutiquesaas.test` / `admin123`, `commercant@demo.test` / `demo1234`, `vendeur@demo.test` / `demo1234`.

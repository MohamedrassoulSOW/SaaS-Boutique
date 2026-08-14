# Guide vidéo — démo réelle stylée + voix française

## Fichier
- `public/videos/ndamstore-guide-nouveaux-utilisateurs.mp4` (~7 min, voix FR)
- Visible sur `/guide#video`

## Contenu
Captures **réelles** de l’interface (layout desktop) + narration française (Microsoft Hortense) :
1. Accueil / Guide
2. **Admin** — vue d’ensemble → journal
3. **Entrepreneur** — dashboard, POS, catalogue, stock, caisse, bénéfices, équipe…
4. **Vendeur** — dashboard, POS, ventes, clients, caisse

## Rebuild
```powershell
php -S 127.0.0.1:8091 -t public public/index.php
cd tools/demo-record
$env:BASE_URL='http://127.0.0.1:8091'
node record_styled.mjs
```

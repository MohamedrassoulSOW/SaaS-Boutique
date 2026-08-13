# Déploiement Hostinger via GitHub

Repository : `https://github.com/MohamedrassoulSOW/SaaS-Boutique.git`  
Branche : `main`

## 1. Préparer l’hébergement Hostinger

1. Créer un site / domaine (ex. `ndamstore.sowcoder.com`).
2. Activer **SSH** (plan Business ou supérieur recommandé).
3. Créer une base **MySQL** (hôte, nom, user, mot de passe).
4. Créer une boîte mail `contact@…` pour SMTP.

## 2. Brancher GitHub → Hostinger

**Panneau Hostinger → Sites → Gérer → Git (ou Déploiement Git) :**

| Champ | Valeur |
|--------|--------|
| Repository | `https://github.com/MohamedrassoulSOW/SaaS-Boutique.git` |
| Branche | `main` |
| Dossier de déploiement | racine du site (ex. `public_html` ou dossier du domaine) |

Si le dépôt est privé : générer un **Personal Access Token** GitHub (scope `repo`) et l’utiliser comme mot de passe Git, ou ajouter la clé SSH Hostinger à GitHub.

Après le premier pull, la structure attendue contient notamment :
`public/`, `bin/`, `config/`, `src/`, `composer.json`, `.htaccess`, `index.php`.

## 3. Document root (important)

**Idéal :** pointer le domaine vers le dossier `public/`  
Hostinger → Domaines → Domaine → **Racine du document** → `…/public`

**Si impossible :** le `.htaccess` + `index.php` à la racine redirigent vers `public/` (déjà inclus dans le repo).

## 4. Secrets sur le serveur (une seule fois)

> **Important :** `APP_ENV=prod` et `APP_DEBUG=0` (sinon cache `var/cache/dev` et pages d’erreur détaillées).

En SSH, à la racine du projet :

```bash
cp .env.prod.local.example .env.prod.local
nano .env.prod.local
```

Renseigner au minimum :

```env
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=   # php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
DEFAULT_URI=https://VOTRE-DOMAINE
DATABASE_URL="mysql://USER:PASSWORD@127.0.0.1:3306/NOM_DB?serverVersion=8.0&charset=utf8mb4"
MAILER_DSN=smtps://contact%40VOTRE-DOMAINE:MOT_DE_PASSE@smtp.hostinger.com:465
MAIL_FROM="NdamStore <contact@VOTRE-DOMAINE>"
PLATFORM_EMAIL=contact@VOTRE-DOMAINE
PLATFORM_TAX_ID="VOTRE-NINEA-REEL"
PLATFORM_PHONE="+221 77 790 14 60"
PLATFORM_PHONE_ALT="+212 684 088765"
```

Ne jamais committer `.env.prod.local`.

## 5. Installer / mettre à jour l’app (SSH)

```bash
cd ~/chemin/vers/le/site
composer prod:deploy
```

Équivalent manuel :

```bash
composer install --no-dev --optimize-autoloader --classmap-authoritative
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
php bin/console app:sessions:init --env=prod
php bin/console cache:clear --env=prod --no-debug
php bin/console cache:warmup --env=prod --no-debug
php bin/console assets:install public --env=prod
php bin/console app:prod:check --env=prod --no-debug
```

PHP requis : **≥ 8.4** (Hostinger → PHP Configuration).

## 6. Cron Hostinger

Tâches planifiées → commande quotidienne (03:00) :

```bash
cd /home/USER/chemin/vers/le/site && php bin/console app:subscriptions:enforce --env=prod --no-debug
```

Voir aussi `tools/crontab.example`.

## 7. Workflow des mises à jour

Sur votre PC :

```bash
git add …
git commit -m "…"
git push origin main
```

Sur Hostinger :

- **Déploiement Git** → Deploy / Pull `main`  
  puis en SSH : `composer prod:deploy`

Ou tout en SSH :

```bash
git pull origin main
composer prod:deploy
```

## 8. Checklist après mise en ligne

- [ ] HTTPS actif (SSL Hostinger)
- [ ] Page d’accueil + `/login` OK
- [ ] Envoi mail (reset MDP / contact)
- [ ] Création vente avec caisse ouverte
- [ ] `app:prod:check` sans erreur bloquante
- [ ] Paiement abonnement Wave/OM : **+221 77 790 14 60**

## Fichiers utiles dans le repo

| Fichier | Rôle |
|---------|------|
| `tools/deploy_prod.php` | Script déploiement |
| `.env.prod.local.example` | Modèle secrets |
| `.htaccess` + `index.php` | Fallback si docroot ≠ `public/` |
| `public/.htaccess` | Rewrite Symfony + headers |
| `tools/crontab.example` | Cron abonnements |

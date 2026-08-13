# NdamStore — Gestion des entreprises

**NdamStore** — *La réussite de votre commerce.*

Plateforme SaaS multi-tenant (Symfony 8 + Twig + Bootstrap 5 + **MySQL**) pour gérer entreprises, produits, stocks, ventes, factures, inventaires et rapports.

## Principe stockage (web + mobile)

**Tout est en base de données** — rien d’opérationnel sur le disque du site :

| Donnée | Stockage |
|--------|----------|
| Produits, stocks, ventes, clients… | Tables Doctrine / MySQL |
| Photos produits | BLOB (`products.photo_data`) |
| Logos entreprises | BLOB (`shops.logo_data`) |
| Factures PDF | BLOB (`invoices.pdf_data`) |
| Entreprise active | `users.preferred_shop_id` |
| Sessions PHP | Table `sessions` (PDO) |

Ainsi une future **app mobile** consommera la même base (ou la même API) sans dépendre des fichiers du serveur web.

## Démarrage rapide (MySQL / WAMP)

1. Démarrez MySQL (WAMP).
2. Dans `.env` (déjà configuré) :

```env
DATABASE_URL="mysql://root:@127.0.0.1:3306/saas_boutique?serverVersion=8.0.32&charset=utf8mb4"
```

3. Puis :

```bash
composer install
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:sessions:init
php bin/console doctrine:fixtures:load --no-interaction
php -S 127.0.0.1:8080 -t public
```

> En production : **uniquement** `doctrine:migrations:migrate` (jamais `schema:update` ni fixtures).

Ouvrir : http://127.0.0.1:8080

## Comptes de démo

| Rôle | Email | Mot de passe | Entreprise |
|------|-------|--------------|----------|
| Admin | admin@boutiquesaas.test | admin123 | (aucune entreprise opérationnelle) |
| Entrepreneur | commercant@demo.test | demo1234 | Plateau |
| Entrepreneur | almadies@demo.test | demo1234 | Almadies |
| Entrepreneur | guediawaye@demo.test | demo1234 | Guédiawaye |
| Entrepreneur | thies@demo.test | demo1234 | Thiès |
| Entrepreneur | mbour@demo.test | demo1234 | Mbour |
| Vendeur | vendeur@demo.test | demo1234 | Plateau (accès créé par l'entrepreneur) |

Chaque entrepreneur n’accède **qu’à sa propre entreprise**. L’admin crée les entreprises sur demande, sans accès aux ventes / stocks.

L'entrepreneur gère les **accès vendeurs** (créer / modifier / supprimer) via le menu **Vendeurs**.

## Emails (réinitialisation mot de passe)

Par défaut : `MAILER_DSN=smtp://127.0.0.1:1025` (Mailpit).

## Modules livrés

- Authentification (connexion, reset, profil) — comptes créés par l'admin (entrepreneurs) et par les entrepreneurs (vendeurs)
- Multi-entreprises + contexte entreprise en BDD
- Produits (photo en BDD), catégories, fournisseurs, clients
- Achats + réception stock
- Ventes POS, remises, paiements, factures PDF en BDD
- Stock, inventaires, rapports, notifications, journal
- Admin plateforme (users, entrepreneurs, création entreprise, abonnements)
- Accès vendeurs (création / modification / suppression par l'entrepreneur)
- Création entreprise admin avec infos entrepreneur + **contrat multi-pages** (PDF en BDD, impression, signature électronique)

## Déploiement production

1. Document root Apache/Nginx → dossier **`public/`** (PHP ≥ 8.4).
2. Sur le serveur :

```bash
cp .env.prod.local.example .env.prod.local
# Éditer : APP_SECRET, DATABASE_URL, MAILER_DSN, PLATFORM_TAX_ID (vrai NINEA)
composer prod:deploy
```

3. Cron quotidien (voir `tools/crontab.example`) :

```cron
0 3 * * * cd /chemin/vers/NdamStore-web && php bin/console app:subscriptions:enforce --env=prod --no-debug
```

4. Contrôles : `composer prod:check` · `php tools/preflight_check.php`  
5. Abonnements : paiement manuel Wave / Orange Money **+221 77 790 14 60**, validation admin.

## App mobile

Hors scope de cette version web. Les données sont déjà centralisées en MySQL pour une API (API Platform / JSON) ultérieure.

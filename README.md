# BoutiqueSaaS — Gestion des boutiques

Plateforme SaaS multi-tenant (Symfony 8 + Twig + Bootstrap 5 + **MySQL**) pour gérer boutiques, produits, stocks, ventes, factures, inventaires et rapports.

## Principe stockage (web + mobile)

**Tout est en base de données** — rien d’opérationnel sur le disque du site :

| Donnée | Stockage |
|--------|----------|
| Produits, stocks, ventes, clients… | Tables Doctrine / MySQL |
| Photos produits | BLOB (`products.photo_data`) |
| Logos boutiques | BLOB (`shops.logo_data`) |
| Factures PDF | BLOB (`invoices.pdf_data`) |
| Boutique active | `users.preferred_shop_id` |
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
php bin/console doctrine:schema:update --force
php bin/console app:sessions:init
php bin/console doctrine:fixtures:load --no-interaction
php -S 127.0.0.1:8080 -t public
```

Ouvrir : http://127.0.0.1:8080

## Comptes de démo

| Rôle | Email | Mot de passe | Boutique |
|------|-------|--------------|----------|
| Admin | admin@boutiquesaas.test | admin123 | (aucune boutique opérationnelle) |
| Commerçant | commercant@demo.test | demo1234 | Plateau |
| Commerçant | almadies@demo.test | demo1234 | Almadies |
| Commerçant | guediawaye@demo.test | demo1234 | Guédiawaye |
| Commerçant | thies@demo.test | demo1234 | Thiès |
| Commerçant | mbour@demo.test | demo1234 | Mbour |
| Vendeur | vendeur@demo.test | demo1234 | Plateau (accès créé par le commerçant) |

Chaque commerçant n’accède **qu’à sa propre boutique**. L’admin crée les boutiques sur demande, sans accès aux ventes / stocks.

Le commerçant gère les **accès vendeurs** (créer / modifier / supprimer) via le menu **Vendeurs**.

## Emails (réinitialisation mot de passe)

Par défaut : `MAILER_DSN=smtp://127.0.0.1:1025` (Mailpit).

## Modules livrés

- Authentification (connexion, reset, profil) — comptes créés par l'admin (commerçants) et par les commerçants (vendeurs)
- Multi-boutiques + contexte boutique en BDD
- Produits (photo en BDD), catégories, fournisseurs, clients
- Achats + réception stock
- Ventes POS, remises, paiements, factures PDF en BDD
- Stock, inventaires, rapports, notifications, journal
- Admin plateforme (users, commerçants, création boutique, abonnements)
- Accès vendeurs (création / modification / suppression par le commerçant)
- Création boutique admin avec infos commerçant + **contrat multi-pages** (PDF en BDD, impression, signature électronique)

## App mobile

Hors scope de cette version web. Les données sont déjà centralisées en MySQL pour une API (API Platform / JSON) ultérieure.

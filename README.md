# BoutiqueSaaS — Gestion des boutiques

Plateforme SaaS multi-tenant (Symfony 8 + Twig + Bootstrap 5 + MySQL/SQLite) pour gérer boutiques, produits, stocks, ventes, factures, inventaires et rapports.

## Démarrage rapide

```bash
composer install
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:schema:update --force
php bin/console doctrine:fixtures:load --no-interaction
php -S 127.0.0.1:8000 -t public
```

Ouvrir : http://127.0.0.1:8000

### Comptes de démo

| Rôle | Email | Mot de passe |
|------|-------|--------------|
| Admin | admin@boutiquesaas.test | admin123 |
| Commerçant | commercant@demo.test | demo1234 |

## MySQL (WAMP)

Dans `.env` :

```env
DATABASE_URL="mysql://root:@127.0.0.1:3306/saas_boutique?serverVersion=8.0.32&charset=utf8mb4"
```

Puis recréez le schéma et rechargez les fixtures.

## Emails (réinitialisation mot de passe)

Par défaut : `MAILER_DSN=smtp://127.0.0.1:1025` (Mailpit).

1. Installez [Mailpit](https://mailpit.axllent.org)
2. Ouvrez l’UI : http://127.0.0.1:8025
3. Demandez un reset via `/reset-password`

Autres options dans `.env` :
```env
# PHP mail()
MAILER_DSN=native://default

# Gmail (mot de passe d'application)
MAILER_DSN=smtp://USER:APP_PASSWORD@smtp.gmail.com:587
MAIL_FROM="BoutiqueSaaS <noreply@votre-domaine.com>"
DEFAULT_URI=http://127.0.0.1:8080
```

## Modules livrés

- Authentification (inscription, connexion, reset, profil)
- Multi-boutiques + contexte boutique
- Produits, catégories, fournisseurs, clients
- Achats + réception stock
- Ventes POS, remises, paiements, factures PDF / impression
- Stock (entrées/sorties/ajustements, alertes)
- Inventaires complets / partiels
- Rapports & tableau de bord (Chart.js)
- Notifications + journal d'activités
- Admin plateforme (users, commerçants, abonnements)

## App mobile

Hors scope de cette version web. Les mêmes APIs métier pourront être exposées ensuite (API Platform / JSON).

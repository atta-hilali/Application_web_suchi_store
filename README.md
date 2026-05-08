# Miya Sushi

Application complète pour restaurant sushi, construite avec Laravel 11, React 18 et Vite.

Le projet reprend le design premium du frontend fourni dans `websushi.html`, mais remplace tout l'état simulé par un vrai backend Laravel avec :

- authentification client/admin
- gestion des commandes
- tableau de bord admin
- gestion admin des produits et catégories
- base de données relationnelle avec seeders
- API REST JSON
- tests fonctionnels

## Stack

- PHP 8.2+
- Laravel 11
- React 18
- Vite 6
- Sanctum en mode SPA cookie/session
- MySQL par défaut
- SQLite supporté pour le local et les tests

## Fonctionnalités

- Accueil premium Miya Sushi
- Carte produits alimentée par la base de données
- Catégories `menu`, `specialty`, `new`
- Inscription / connexion / déconnexion
- Panier côté frontend
- Commandes livraison ou à emporter
- Validation d'adresse obligatoire pour la livraison
- Historique "Mes Commandes"
- Statuts de commande
- Dashboard admin avec statistiques
- Acceptation / refus des commandes
- CRUD admin produits et catégories
- Upload image produit ou URL distante

## Prérequis

- PHP 8.2 ou supérieur
- Composer
- Node.js 20+ et npm
- MySQL 8+ ou SQLite

Note Windows :

- dans cet environnement, les commandes PHP ont été exécutées avec `C:\xampp\php\php.exe`
- l'extension PHP `zip` doit être activée pour accélérer Composer

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

## Configuration `.env`

Le fichier `.env.example` est configuré pour MySQL par défaut.

Exemple MySQL :

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=miya_sushi
DB_USERNAME=root
DB_PASSWORD=
```

Exemple SQLite local :

```env
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

Si vous utilisez SQLite localement :

```bash
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
```

Variables utiles déjà prévues :

- `SANCTUM_STATEFUL_DOMAINS`
- `CORS_ALLOWED_ORIGINS`
- `FILESYSTEM_DISK=public`

## Démarrage local

### Backend

```bash
php artisan serve
```

### Frontend en mode dev

```bash
npm run dev
```

### Build production

```bash
npm run build
```

## Base de données et seeders

Les seeders créent :

- catégories :
  - `Menu Classique`
  - `Spécialités`
  - `Nouveautés`
- les 9 produits du frontend source
- réglages restaurant
- un administrateur
- un client démo

Commandes utiles :

```bash
php artisan migrate:fresh --seed
php artisan storage:link
```

## Comptes par défaut

Admin :

- email : `admin@gmail.com`
- mot de passe : `123`

Client démo :

- email : `client@gmail.com`
- mot de passe : `12345678`

Important :

- changez immédiatement le mot de passe admin en production

## Routes API

### Public

- `GET /api/products`
- `GET /api/products?category=menu`
- `GET /api/products?category=specialty`
- `GET /api/products?category=new`
- `GET /api/categories`
- `POST /api/register`
- `POST /api/login`

### Authentifié

- `POST /api/logout`
- `GET /api/me`
- `GET /api/orders`
- `POST /api/orders`
- `GET /api/orders/{order}`

### Admin

- `GET /api/admin/dashboard`
- `GET /api/admin/orders`
- `GET /api/admin/orders/{order}`
- `PATCH /api/admin/orders/{order}/accept`
- `PATCH /api/admin/orders/{order}/reject`
- `GET /api/admin/products`
- `POST /api/admin/products`
- `GET /api/admin/products/{product}`
- `PUT /api/admin/products/{product}`
- `DELETE /api/admin/products/{product}`
- `PATCH /api/admin/products/{product}/toggle-availability`
- `GET /api/admin/categories`
- `POST /api/admin/categories`
- `PUT /api/admin/categories/{category}`
- `DELETE /api/admin/categories/{category}`

## Payload création de commande

```json
{
  "delivery_type": "delivery",
  "delivery_address": "123 rue exemple",
  "comments": "No wasabi please",
  "items": [
    {
      "product_id": 1,
      "quantity": 2
    },
    {
      "product_id": 4,
      "quantity": 1
    }
  ]
}
```

Le backend :

- recharge les produits depuis la base
- vérifie la disponibilité
- calcule `subtotal`, `delivery_fee`, `total`
- snapshotte `product_name` et `product_price`
- crée la commande dans une transaction SQL

## Architecture

Backend :

- `app/Enums` : rôles, statuts, types de livraison
- `app/Http/Controllers/Api` : endpoints publics, client et admin
- `app/Http/Requests` : validation
- `app/Http/Resources` : réponses JSON
- `app/Policies` : autorisation par commande
- `app/Services/OrderService.php` : logique de création de commande

Frontend :

- `resources/js/App.jsx`
- `resources/js/components/*`
- `resources/css/app.css`
- `resources/views/app.blade.php`

## Tests

Les tests couvrent :

1. inscription client
2. connexion client
3. connexion admin
4. listing public des produits
5. création de commande client
6. protection contre les faux prix frontend
7. interdiction d'accès à la commande d'un autre client
8. listing admin des commandes
9. acceptation admin d'une commande
10. refus admin d'une commande
11. refus d'accès admin pour un non-admin

Lancer les tests :

```bash
php artisan test
```

## Déploiement

Checklist production :

1. renseigner un vrai `.env`
   - vous pouvez partir de `/.env.production.example`
2. définir `APP_ENV=production`
3. définir `APP_DEBUG=false`
4. configurer la base MySQL
5. exécuter `composer install --no-dev --optimize-autoloader`
6. exécuter `php artisan migrate --force`
7. exécuter `php artisan storage:link`
8. exécuter `npm install && npm run build`
9. exécuter :

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

10. vérifier que `public/` est bien le document root
11. vérifier les permissions d'écriture pour `storage/` et `bootstrap/cache/`
12. si vous ajoutez plus tard des notifications/jobs, configurer un worker queue

### Modèle `.env` production

Un modèle prêt à remplir est disponible ici :

- `.env.production.example`

Usage recommandé :

```bash
cp .env.production.example .env
php artisan key:generate
```

## Dépannage

### `Session store not set on request`

Vérifiez que :

- les routes SPA/API passent bien par Sanctum + middleware `web`
- le cookie/session est autorisé sur votre domaine local

### `Database file does not exist`

Pour SQLite :

```bash
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
```

### `Class ... not found`

```bash
composer dump-autoload
```

### Les images uploadées ne s'affichent pas

```bash
php artisan storage:link
```

### Le front Vite ne charge pas

```bash
npm install
npm run dev
```

## Vérifications effectuées

Commandes exécutées pendant l'intégration :

- `php artisan migrate:fresh --seed`
- `php artisan test`
- `npm run build`
- `php artisan storage:link`

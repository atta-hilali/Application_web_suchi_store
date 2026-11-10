# Guide hébergement et prérequis — Miya Sushi

**À transmettre à l’hébergeur ou au développeur chargé de la mise en ligne.**

Le client prend en charge le domaine, l’hébergement et l’installation sur le serveur, selon l’accord de livraison. La personne chargée de l’installation suit les étapes ci-dessous. Ce document explique une procédure ; il ne signifie pas que le site a déjà été déployé chez un hébergeur.

## 1. Ce que le client doit fournir

- Un domaine et l’accès à sa gestion DNS.
- Un hébergement répondant au tableau ci-dessous et une personne chargée de l’installation.
- Une base de données et ses accès, transmis de façon privée.
- Le menu, les prix en TND, les coordonnées, les horaires et les règles de livraison approuvés.
- L’adresse email du propriétaire du compte administrateur.

**Message à envoyer à l’hébergeur :**

> Je souhaite installer une application Laravel 12 avec React/Vite. Merci de confirmer la compatibilité PHP 8.2 ou supérieur, MySQL/MariaDB, Composer 2, les commandes Artisan, HTTPS, une racine web pointant vers le dossier public/, le lien public/storage et un stockage persistant. Le site accepte des vidéos de 30 Mo : les limites PHP et serveur doivent permettre cet envoi. Pouvez-vous assurer l’installation et les sauvegardes ?

## 2. Prérequis techniques

| Élément | Exigence pour ce projet |
| --- | --- |
| PHP | 8.2 minimum, version maintenue et à jour. Vérifier la version du terminal **et** celle du serveur web. |
| Extensions PHP | Ctype, cURL, DOM, Fileinfo, Filter, Hash, Mbstring, OpenSSL, PCRE, PDO, `pdo_mysql`, Session, Tokenizer et XML. Zip ou un outil de décompression doit être disponible pour Composer. |
| Base de données | MySQL 8+ ou une version maintenue de MariaDB compatible avec Laravel, avec prise en charge des transactions. Tester sur le moteur réellement choisi. |
| Serveur web | Nginx ou Apache configuré pour Laravel. La racine web doit être le dossier `public/`. |
| Accès de déploiement | SSH/terminal ou un service de déploiement capable d’exécuter Composer et Artisan. |
| Composer | Composer 2 et installation depuis `composer.lock`. |
| Compilation du frontend | Node.js 22.12+ dans la branche 22, ou Node.js 24, avec npm. La compilation peut se faire sur une machine de préparation ; Node n’a pas besoin de rester lancé sur le serveur web. |
| HTTPS | Certificat valide et renouvellement configuré. |
| Stockage | Disque persistant pour `storage/app/public/`, y compris les images des produits et les médias d’accueil. |
| Droits | Le processus PHP doit pouvoir écrire dans `storage/` et `bootstrap/cache/`. Garder des droits limités au compte de déploiement et au serveur web. |
| Envoi des médias | `upload_max_filesize=30M` minimum ; `post_max_size=40M` ou plus ; limite du serveur/proxy au moins 40 Mo, par exemple `client_max_body_size 40m;` avec Nginx. |
| Sauvegardes | Sauvegardes automatiques de la base et des fichiers importés, et procédure de restauration testée. |

Un hébergement uniquement statique ne suffit pas pour cette application : les comptes et les commandes nécessitent le backend PHP et la base de données. Le serveur de développement `php artisan serve` et Vite servent aux essais locaux ; la production utilise Nginx/Apache avec PHP.

## 3. Installation sur une base vierge

Commandes à exécuter par la personne technique, depuis le répertoire du projet. Les exemples utilisent un terminal Linux ; adaptez les chemins et les noms au serveur.

### Étape A — Récupérer le projet

```bash
git clone --branch main https://github.com/atta-hilali/Application_web_suchi_store.git
cd Application_web_suchi_store
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
cp .env.production.example .env
```

Une archive source peut remplacer le clonage. Elle doit contenir les fichiers cachés utiles, les deux lockfiles, `artisan`, les dossiers de l’application et `public/videos/`.

Le dépôt ne contient ni `vendor/` ni `public/build/` : les étapes d’installation et de compilation les produisent. Si le serveur ne possède pas Node, compilez ailleurs avec la même révision et transmettez le dossier `public/build/` complet. Si l’hébergeur prépare un paquet avec `vendor/`, il doit vérifier sa compatibilité avec le PHP et les extensions de la cible.

### Étape B — Créer la base et configurer `.env`

Créer une base vide et un utilisateur dédié disposant des droits nécessaires aux tables et aux migrations. Remplacer les valeurs de modèle dans `.env`. Exemple pour un domaine unique :

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://restaurant.example
APP_TIMEZONE=Africa/Tunis

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=NOM_BASE
DB_USERNAME=UTILISATEUR_BASE
DB_PASSWORD="MOT_DE_PASSE_A_RENSEIGNER_EN_PRIVE"

SESSION_DRIVER=database
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
SANCTUM_STATEFUL_DOMAINS=restaurant.example
CORS_ALLOWED_ORIGINS=https://restaurant.example

CACHE_STORE=database
FILESYSTEM_DISK=public
QUEUE_CONNECTION=database
MAIL_MAILER=log
```

`restaurant.example` est un exemple à remplacer par le véritable domaine. Utiliser un domaine canonique et rediriger les autres variantes vers celui-ci. Si plusieurs sous-domaines doivent partager la connexion, l’installateur doit adapter explicitement les cookies et les domaines autorisés, puis tester le résultat.

La version actuelle n’envoie pas de confirmation de commande par email et n’intègre ni paiement en ligne ni récupération de mot de passe. `MAIL_MAILER=log` convient à ce périmètre ; les champs SMTP du modèle ne constituent pas une fonctionnalité d’envoi déjà installée.

Le fichier `.env`, les mots de passe et les sauvegardes privées restent hors de GitHub et hors du répertoire public.

### Étape C — Initialiser la nouvelle application

```bash
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --class=RestaurantSettingSeeder --force
php artisan storage:link
```

Générer `APP_KEY` **une seule fois pour cette nouvelle installation**, puis le sauvegarder de façon privée. Conserver cette même clé lors des mises à jour et des restaurations.

Sur une installation client, utiliser uniquement l’initialisation ciblée ci-dessus. Le `db:seed` général installe des comptes de démonstration connus et peut réécrire le catalogue ; `migrate:fresh` efface les tables. Ces commandes ne font pas partie de cette procédure de production.

### Étape D — Compiler le frontend

Sur le serveur ou sur la machine de préparation :

```bash
npm ci
npm run build
```

Les dépendances de développement sont nécessaires pendant cette compilation. Déployer `public/build/manifest.json` et les fichiers d’assets correspondants ensemble. Vérifier que le paquet déployé ne contient pas de fichier `public/hot` provenant de Vite.

### Étape E — Configurer le serveur et HTTPS

- Faire pointer le domaine vers le serveur et activer son certificat HTTPS.
- Configurer la racine web sur `/CHEMIN_DU_PROJET/public`, avec traitement PHP de `public/index.php`.
- Avec Nginx, router les URL de l’application via `try_files $uri $uri/ /index.php?$query_string;` et adapter PHP-FPM à l’hébergement.
- Avec Apache, activer la réécriture et autoriser les règles du fichier `public/.htaccess` fourni.
- Autoriser l’écriture dans `storage/` et `bootstrap/cache/` avec le propriétaire/groupe adaptés.
- Vérifier le lien `public/storage` vers `storage/app/public`. Préserver ce dernier dossier lors des prochains déploiements.
- Configurer les limites d’envoi indiquées dans les prérequis. Si un proxy est utilisé, configurer les proxys de confiance pour HTTPS et la bonne identification des IP clientes.

Ne pas déplacer `index.php` à la racine du projet : seule la partie `public/` doit être exposée. Pour une configuration Nginx complète, utiliser la documentation Laravel référencée en fin de guide et l’adapter au serveur.

### Étape F — Créer le compte du propriétaire

Sur la nouvelle installation protégée ou avant l’ouverture commerciale :

1. Ouvrir le site en HTTPS et choisir **Connexion → S’inscrire**.
2. Créer le compte du propriétaire avec son véritable email et un mot de passe personnel solide.
3. Dans le terminal du serveur, lancer `php artisan tinker`, puis exécuter ceci en remplaçant l’email :

   ```php
   $user = \App\Models\User::where('email', 'EMAIL_DU_PROPRIETAIRE')->firstOrFail();
   $user->role = \App\Enums\UserRole::ADMIN;
   $user->save();
   ```

4. Quitter Tinker avec `exit`, puis se déconnecter/reconnecter sur le site. **Admin Panel** apparaît.
5. Renseigner le menu réel, les prix, les horaires et la livraison dans l’admin. La livraison est désactivée tant qu’aucune zone approuvée n’est activée.
6. Choisir l’image ou la vidéo du restaurant dans **Fond d’accueil**.

La création de ce compte se fait en privé : aucun mot de passe réel n’est à écrire dans ce guide. Le projet contient encore un indice de connexion de démonstration ; les comptes correspondants doivent être absents ou sécurisés avant ouverture. Leur modification a été différée par le propriétaire du projet.

### Étape G — Préparer les caches

Une fois les variables et la base configurées :

```bash
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Les actualisations des commandes sont effectuées par le navigateur ouvert. Aucun service d’alerte SMS/email ni worker de notifications n’est nécessaire dans la version actuelle. Si des tâches en file d’attente sont ajoutées plus tard, leur worker devra alors être configuré.

## 4. Reprise d’une base ou mise à jour d’un site existant

Cette situation remplace l’installation sur base vierge :

1. Sauvegarder la base, `storage/app/public/`, la configuration privée et la clé `APP_KEY` ; conserver la version précédente du code.
2. Pour un transfert, restaurer la base autorisée avec sa table `migrations`, restaurer les fichiers importés et reprendre la clé existante. Adapter domaine, accès base et cookies au nouveau serveur.
3. Déployer la version convenue, installer depuis les lockfiles et compiler les assets comme indiqué plus haut.
4. Exécuter `php artisan migrate --force`, vérifier le lien de stockage et reconstruire les caches.
5. Vérifier les comptes existants ; ne pas recréer un compte propriétaire en double et ne pas relancer les seeders de démonstration.
6. Tester sur un environnement de validation MySQL/MariaDB avant ouverture. Prévoir une fenêtre de maintenance pour les changements de schéma et une restauration coordonnée code/base/fichiers si nécessaire.

La migration TND conserve les anciens montants sans conversion de taux et marque les commandes historiques EUR. Les nouvelles commandes sont en TND à trois décimales. Vérifier aussi le fuseau horaire des anciennes données lors d’une reprise réelle.

## 5. Vérifications avant remise au client

- [ ] Domaine et HTTPS fonctionnent ; la page d’accueil et les images/vidéos chargent.
- [ ] `/up` répond et le menu est lisible depuis la base ; le test de santé seul ne valide pas les commandes.
- [ ] Inscription, connexion et déconnexion fonctionnent sur le domaine final.
- [ ] Le client peut commander à emporter ; la livraison fonctionne uniquement dans les zones et horaires configurés.
- [ ] Frais, minimum, total en TND et heure de Tunis sont corrects.
- [ ] L’admin reçoit la commande dans sa liste, peut accepter/refuser ; le client voit le statut mis à jour.
- [ ] L’admin peut modifier un produit et importer une image ou une vidéo ; tester un fichier vidéo proche de la limite de 30 Mo.
- [ ] Aucun compte de démonstration utilisable n’est accessible publiquement ; `APP_DEBUG=false`.
- [ ] Une sauvegarde de la base et des médias a été restaurée avec succès dans un environnement séparé.
- [ ] Le client reçoit l’URL, son accès administrateur, les accès d’hébergement convenus et le contact de support.

## 6. Sauvegardes et incidents courants

L’hébergeur doit planifier au minimum une sauvegarde quotidienne de la base et des médias, en adaptant la fréquence au volume des commandes et à la perte de données acceptable pour le client. Conserver une copie hors du serveur, protéger les accès, convenir de la durée de conservation et tester la restauration. Sauvegarder également la configuration et `APP_KEY` de manière sécurisée.

| Problème | Vérification à effectuer |
| --- | --- |
| Page blanche / erreur 500 | Examiner les journaux serveur et `storage/logs/laravel.log`, les extensions PHP, `.env`, `APP_KEY` et les droits d’écriture. Garder le débogage public désactivé. |
| Connexion ou erreur CSRF 419 | Vérifier HTTPS, domaine canonique, cookies et variables Sanctum ; après correction, reconstruire le cache de configuration et réessayer dans une nouvelle session. |
| Assets ou manifeste absents | Effectuer la compilation et déployer le dossier `public/build/` complet de la bonne version. |
| Images/vidéos importées absentes | Vérifier `public/storage`, `APP_URL`, les fichiers persistants et leurs droits. |
| Envoi vidéo refusé / erreur 413 | Vérifier les limites PHP, Nginx/Apache et du proxy ; l’application limite une vidéo à 30 Mo. |
| Livraison ou commande refusée | Vérifier les paramètres admin : activation, horaires de Tunis, dates de fermeture, codes postaux, minimum et disponibilité des produits. |

## Références

- [Déploiement Laravel 12 : PHP, racine publique et caches](https://laravel.com/docs/12.x/deployment).
- [Stockage public Laravel et lien `storage:link`](https://laravel.com/docs/12.x/filesystem#the-public-disk).
- [Fonctionnement propre à Miya Sushi](../OPERATIONS.md).

Ce guide a été vérifié par rapport au code du dépôt. La compatibilité de l’hébergement choisi et l’installation réelle restent à valider par la personne qui met le site en ligne.

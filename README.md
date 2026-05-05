# CityLunch – Application de commande de repas

Application Symfony permettant aux clients de consulter le menu du jour, créer un compte, se connecter et gérer un panier.

---

## Stack technique

| Composant | Version |
|---|---|
| PHP | 8.2+ |
| Symfony | 7.2 |
| MySQL (WAMP) | 8.0 |
| MongoDB (sessions) | 7.x |
| Twig | 3.x |
| Bootstrap | 5.3 |

---

## 1. Prérequis : activer l'extension MongoDB dans WAMP

WAMP ne livre pas `mongodb.dll` par défaut. Voici la procédure exacte :

### a) Identifier votre version PHP dans WAMP
Dans WAMP, cliquez sur l'icône → PHP → Version → notez la version (ex. `8.2.12`).
Notez aussi si vous êtes en **Thread Safe (TS)** ou **Non Thread Safe (NTS)** :
- Apache + WAMP = **Thread Safe (TS)**
- Architecture : **x64** sur tout PC moderne

### b) Télécharger la DLL
Rendez-vous sur **[PECL MongoDB](https://pecl.php.net/package/mongodb)** ou directement sur :
👉 **https://windows.php.net/downloads/pecl/releases/mongodb/**

Choisissez la version `1.20.x` et téléchargez le fichier correspondant à votre PHP.
Exemple pour PHP 8.2 TS x64 :
```
php_mongodb-1.20.0-8.2-ts-vs16-x64.zip
```

### c) Installer la DLL
1. Décompressez le zip
2. Copiez `php_mongodb.dll` dans : `C:\wamp64\bin\php\php8.2.x\ext\`
3. Copiez les éventuels fichiers `.dll` (libssl, libcrypto) dans `C:\wamp64\bin\php\php8.2.x\`

### d) Activer l'extension
Ouvrez `C:\wamp64\bin\php\php8.2.x\php.ini` et ajoutez (ou décommentez) :
```ini
extension=mongodb
```

### e) Redémarrer WAMP
Cliquez sur l'icône WAMP → **Restart All Services**.

### f) Vérifier
```bash
php -m | findstr mongodb
```
Vous devez voir `mongodb` dans la liste.

---

## 2. Installation du projet

### Cloner/copier le projet
```bash
cd C:\wamp64\www
# Copiez le dossier citylunch ici, puis :
cd citylunch
```

### Installer les dépendances PHP
```bash
composer install
```

### Configurer l'environnement
Le fichier `.env` est déjà configuré pour WAMP par défaut :
```dotenv
DATABASE_URL="mysql://root:@127.0.0.1:3306/citylunch?serverVersion=8.0&charset=utf8mb4"
MONGODB_URL=mongodb://127.0.0.1:27017
MONGODB_DB=citylunch_sessions
```

Si votre MySQL a un mot de passe root, adaptez : `mysql://root:MON_MDP@127.0.0.1:3306/citylunch...`

---

## 3. Initialisation de la base de données MySQL

```bash
# Créer la base de données
php bin/console doctrine:database:create

# Créer les tables via les migrations
php bin/console doctrine:migrations:migrate

# (Optionnel mais recommandé) Charger les données de démonstration
php bin/console doctrine:fixtures:load
```

Les fixtures créent :
- 3 clients : `alice@citylunch.fr`, `bob@citylunch.fr`, `admin@citylunch.fr` (mot de passe : `password123`, `admin1234`)
- 3 plats + 2 desserts du jour

---

## 4. MongoDB – sessions - 

Note : En raison d'un conflit de version entre la DLL PECL MongoDB et la version PHP locale (WAMP), l'extension n'a pas pu être activée. Les sessions utilisent donc le handler PHP natif (fichiers). Le MongoDbSessionHandler reste présent dans le code (src/Session/) mais n'est pas activé ni testé.

MongoDB doit tourner localement sur le port `27017`.
Téléchargez et installez **[MongoDB Community Server](https://www.mongodb.com/try/download/community)**.

Le `MongoDbSessionHandler` crée automatiquement la collection `sessions` et son index TTL au premier démarrage. Aucune configuration manuelle n'est nécessaire.

---

## 5. Lancer l'application

### Via WAMP (Apache)
1. Copiez le projet dans `C:\wamp64\www\citylunch`
2. Dans WAMP, créez un VirtualHost pointant sur `C:\wamp64\www\citylunch\public`  
   OU accédez directement via : `http://localhost/citylunch/public/`

### Via le serveur intégré Symfony (recommandé en dev)
```bash
# Installer le CLI Symfony si absent : https://symfony.com/download
symfony server:start
# Puis ouvrir : http://127.0.0.1:8000
```

### Via PHP built-in server
```bash
php -S localhost:8000 -t public/
```

---

## 6. Commandes utiles

```bash
# Vider le cache
php bin/console cache:clear

# Voir les routes
php bin/console debug:router

# Générer une migration après modification d'entité
php bin/console make:migration
php bin/console doctrine:migrations:migrate

# Recharger les fixtures (⚠️ efface les données)
php bin/console doctrine:fixtures:load --no-interaction
```

---

## 7. Choix des deux bases de données

### MySQL – données relationnelles
Les données métier (produits, clients, articles du panier) sont **structurées et liées par des relations** (un client a plusieurs articles, un article référence un produit). Une base relationnelle avec des jointures, contraintes d'intégrité et transactions ACID est le choix naturel. MySQL est inclus dans WAMP et parfaitement supporté par Doctrine ORM.

### MongoDB – sessions utilisateur
Les sessions sont des **documents éphémères, sans schéma fixe, à durée de vie limitée**. MongoDB excelle pour ce cas :
- **Index TTL natif** : les sessions expirées sont supprimées automatiquement, sans cron ni GC PHP
- **Lecture/écriture rapide** : accès par `_id` (session ID), aucune jointure nécessaire
- **Flexibilité** : le contenu d'une session peut évoluer librement
- **Scalabilité** : MongoDB supporte bien la montée en charge d'un service de sessions

---

## 8. Stratégie de sauvegarde

### MySQL
- **Sauvegarde quotidienne complète** avec `mysqldump` :
  ```bash
  mysqldump -u root citylunch > backup_citylunch_YYYYMMDD.sql
  ```
- **Rétention** : 7 jours glissants (supprimer les fichiers de plus de 7 jours)
- **En production** : activer le binlog MySQL pour une restauration point-in-time (PITR)

### MongoDB (sessions)
- Les sessions sont **éphémères** : une perte de données entraîne au pire une déconnexion des utilisateurs, sans perte métier
- Sauvegarde optionnelle avec `mongodump` si souhaité
- L'index TTL assure le nettoyage automatique, pas besoin de sauvegarde critique

---

## 9. Recommandation SSL

Pour ce projet en développement **local WAMP**, SSL n'est pas nécessaire.

En **production** ou sur un serveur accessible depuis Internet :
- Utiliser **Let's Encrypt** (certificat gratuit, renouvelable automatiquement via Certbot)
- Configurer le VirtualHost Apache pour forcer HTTPS :
  ```apache
  <VirtualHost *:443>
      SSLEngine on
      SSLCertificateFile    /etc/letsencrypt/live/citylunch.fr/fullchain.pem
      SSLCertificateKeyFile /etc/letsencrypt/live/citylunch.fr/privkey.pem
      DocumentRoot /var/www/citylunch/public
  </VirtualHost>
  ```
- Ajouter dans `.env` : `COOKIE_SECURE=true` et activer `cookie_secure: true` dans `framework.yaml`

---

## 10. IDE – Recommandations

| Plugin | IDE | Utilité |
|---|---|---|
| Symfony Support | PhpStorm | Autocomplétion routes, services, Twig |
| PHP Annotations | PhpStorm / VS Code | Support attributs PHP 8 |
| Twig | VS Code (ext. `whatwedo`) | Coloration syntaxique Twig |
| PHP Intelephense | VS Code | Autocomplétion PHP avancée |
| MongoDB for VS Code | VS Code | Visualiser les collections MongoDB |

---

## Dépôt Git

https://github.com/matgm04/CityLunch.git

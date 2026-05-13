# Deploiement Railway - Symfony + MySQL

Ce projet est prepare pour Railway avec Railpack et une base MySQL.

## 1. Service web Symfony

Dans Railway, cree un nouveau projet puis ajoute le repo GitHub du dossier `projet_symfony`.

Si ton repo GitHub contient aussi d'autres dossiers (`model`, `WorkshopJdbc-3A14`, etc.), configure le service Railway avec :

```text
Root Directory: /projet_symfony
Config File: /projet_symfony/railway.json
```

Si le repo GitHub commence directement dans `projet_symfony`, laisse ces champs par defaut.

## 2. Base de donnees MySQL

Dans le meme projet Railway :

1. Clique sur `+ New`.
2. Choisis `Database`.
3. Choisis `MySQL`.

Railway cree automatiquement les variables `MYSQLHOST`, `MYSQLPORT`, `MYSQLUSER`, `MYSQLPASSWORD`, `MYSQLDATABASE` et `MYSQL_URL`.

## 3. Variables du service Symfony

Dans le service Symfony, onglet `Variables`, ajoute :

```text
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=remplace-moi-par-une-cle-secrete-longue
COMPOSER_ALLOW_SUPERUSER=1
RAILPACK_PHP_ROOT_DIR=/app/public
RAILPACK_PHP_EXTENSIONS=pdo_mysql,gd,intl,zip,mbstring,curl
DATABASE_URL=${{MySQL.MYSQL_URL}}?serverVersion=9.4.0&charset=utf8mb4
```

Si ton service MySQL ne s'appelle pas `MySQL`, remplace `MySQL` dans `${{MySQL.MYSQL_URL}}` par le nom exact du service Railway.

## 4. Importer les tables et donnees

Le fichier SQL principal est :

```text
voyage.sql
```

Options possibles :

- Dans Railway, ouvre le service MySQL et utilise l'interface de base de donnees pour executer le contenu de `voyage.sql`.
- Avec Railway CLI, connecte-toi a la base MySQL puis execute `source voyage.sql;` depuis le client MySQL.

Il faut importer `voyage.sql` une seule fois, avant de tester le site.

## 5. Deployer et ouvrir le site

1. Lance un redeploiement du service Symfony.
2. Ouvre les logs Railway et verifie qu'il n'y a pas d'erreur `DATABASE_URL`, `APP_SECRET` ou extension PHP.
3. Dans `Settings > Networking`, clique `Generate Domain`.
4. Ouvre l'URL Railway generee.

## Note sur les uploads

Les fichiers envoyes dans `public/uploads` peuvent disparaitre apres un redeploiement si aucun volume Railway n'est attache. Pour garder les images/videos uploades, ajoute un volume sur :

```text
/app/public/uploads
```

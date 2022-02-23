# Statistiques ENT - frequentation portail

## Prérequis technique

Le portail nécessite les prérequis techniques suivants : 
* OS : Linux (Debian ou Centos, de préférences)
* Apache ou Nginx
* PHP 7.2
* Mysql / MariaDB 5.x
* RAM 2 Gb min.

## Étapes d’installation

### Installation des fichiers

#### Dans tous les cas


Copier les fichiers du dépôt git dans le dossier `/var/www/[APPLICATION]`.

Renommer le fichier `include/config-dist.php` en `include/config.php` et y placer les bonnes informations.
#### Installation de l'import et de la partie web au même endroit ou de la partie web web seulement

Installer les dépendances :
```
composer install --no-dev
```

Installer les dépendances web _(inutile pour la partie import)_ :
```
npm install -production
npm run build-prod
```

### Installation de la base de données

L'installation de la base de données s'effectue via l'import du dump SQL suivant : `/data/frequentation_portail_structure.sql`.

La commande suivante permet d'importer la base de données :
```bash
mysql -h HOST -P PORT -D DATABASE -u USER -p < data/frequentation_portail_structure.sql
```

### Installation du cache twig

A la racine du projet, ajouter un dossier cache er lui donner les bon droits :
```bash
sudo mkdir cache
sudo chown www-data:www-data cache
sudo chmod g+w cache
```

Puis vérifier que ce dossier correspond bien a celui spécifié dans la conf pour la clé `twig/cacheDir`.

## Étapes de paramétrage du serveur

### PHP

Extensions nécessaires :
* PDO
* PDO_mysql
* Xml
* MBstring
* ldap

### MariaDB

Le fichier de configuration MySQL est modifié pour augmenter la taille des paquets traités.
A cet effet, la valeur suivante :
`max_allowed_packet = 128M`
Est définie au sein du fichier :
`/etc/my.cnf`

### Apache

Le fichier de configuration PHP est situé à cet emplacement : 
`/etc/httpd/sites-enabled/[A PRECISER]`
Le paramétrage fournit par défaut par l’hébergeur permet une utilisation performante du portail.

### Certificat de sécurité (HTTPS)

Le portail s’appuie sur l’utilisation d’un certificat SSL mis en place par l’hébergeur.

### Authentification CAS

Le portail s’appuie sur une authentification via le protocole CAS.

### ldap

Le portail utilise ldap pour déterminer les droits de l'utilisateur

## Étapes de paramétrage de l’application

### Base de données

L’accès à la base de données est défini dans le fichier `/include/config.php` dans la section `db`.

### CAS Recia

La configuration du cas est défini dans le fichier `/include/config.php` dans la section `cas`.
La configuration cas n'est pas nécessaire pour la partie import toute seule.

### ldap

La configuration du cas est défini dans le fichier `/include/config.php` dans la section `ldap`.
La configuration cas n'est pas nécessaire pour la partie import toute seule.
### Emplacement des fichiers d'import

Il est possible, mais pas obligatoire, de définir l'emplacement des fichiers d'import dans le fichier `/include/config.php` dans la section `importDir`.

## Import des données

### Liste des paramètres

* `-d` pour **date**, optionnel au format YYYY/MM (prioritaire sur le `-y`)
* `-c` pour **chemin**, optionnel en format absolu
* `-v` pour avoir des logs plus verbeux
* `-y` pour importer à la date d'hier
Des messages d’erreurs s’afficheront si le dossier n’existe pas.

### Traitement automatique

Il convient d’installer une tâche CRON pour l’import des données.
L’import s’appuie sur des données mensuelles.
Il est possible d’appeler le programme 1 fois par jour. Dans ce cas, les informations du mois importé se complètent au fur et à mesure.
La commande à appeler :
```bash
php import.php
```

Dans le cas d’un traitement automatique, le programme importe automatiquement, le mois en cours.

### Traitement manuel

Il est possible d’importer manuellement un mois donné (cas d’un import de mois précédents).
La commande à appeler : 

php import.php -d 2020/05

### Traitement dans un dossier précis

Par défaut, le dossier utilisé est celui précisé dans la conf.
Il est possible de surcharger cette valeur en la spécifiant dans la ligne de commande.
La commande à appeler :
```
php import.php -d 2020/05 -c /chemin/vers/dossier
```

## Les droits dans l'application

### Utilisateur ayant un attribut ESCOSIRENCourant

Les utilisateur ayant un attribut **ESCOSIRENCourant** ne pourront accéder qu'aux statistiques de cet établissement.

La liste de sélection d'établissement sera donc désactivée, et la vue établissement n'affichera que l'établissement en question.

### Utilisateur ayant le role National_DIR

Les utilisateurs ayant le role **National_DIR** dans le champ **ENTPersonProfils** ne verront que leur établissement, les champs de sélections suivants seront donc masqués :
* établissement
* type
* mois
* sélecteur du type de vue

L'affichage de statistiques se fera donc pour **Tous les mois** et non pour un mois précis et sur la vue **services**.

De plus l'affichage **TOP** sera masqué.


### Utilisateur membre du groupe esco:admin:Indicateurs:central

L'utilisateur membre du groupe **esco:admin:Indicateurs:central** verra tous les lycées.

### Utilisateur membre du groupe clg%DEP%:admin:Indicateurs:central

L'utilisateur membre du groupe **clg%DEP%:admin:Indicateurs:central** verra tous les collèges du département %DEP%.
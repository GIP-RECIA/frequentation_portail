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

#### Installation de l'import uniquement

Ne conserver que les fichiers suivants :
* `import.php`
* `include/config.php`
* `include/import-functions.php`

### Installation de la base de données

L'installation de la base de données s'effectue via l'import du dump SQL suivant : `/data/frequentation_portail_structure.sql`.

La commande suivante permet d'importer la base de données :
```bash
mysql -h HOST -P PORT -D DATABASE -u USER -p < data/frequentation_portail_structure.sql
```

## Étapes de paramétrage du serveur

### PHP

Extensions nécessaires :
* PDO
* Mysqli
* Xml
* MBstring

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

## Étapes de paramétrage de l’application

### Base de données

L’accès à la base de données est défini dans le fichier `/include/config.php` dans la section `db`.

### CAS Recia

La configuration du cas est défini dans le fichier `/include/config.php` dans la section `cas`.
La configuration cas n'est pas nécessaire pour la partie import toute seule.

### Emplacement des fichiers d'import

Il est possible, mais pas obligatoire, de définir l'emplacement des fichiers d'import dans le fichier `/include/config.php` dans la section `importDir`.

## Import des données

### Liste des paramètres

* `-d` pour **date**, optionnel au format YYYY/MM
* `-c` pour **chemin**, optionnel en format absolu
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
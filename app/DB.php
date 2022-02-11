<?php

namespace App;

use PDO;
use App\Config;

/**
 * Permet de récupérer la connexion vers la db
 */
class DB {
    private static $PDO = null;

    /**
     * Le constructeur avec sa logique est privé pour empêcher l'instanciation en dehors de la classe
     **/
    private function __construct() {}

    /**
     *  Permet d'obtenir l'objet PDO
     *
     *  @return PDO L'objet pdo pour se connecter à la db
     **/
    public static function getPdo(): PDO {
        if (self::$PDO === null) {
            $conf = (Config::getInstance())->get('db');
            self::$PDO = new PDO(
                "mysql:host={$conf['host']};port={$conf['port']};dbname={$conf['dbName']};charset=utf8",
                $conf['user'],
                $conf['password']
            );
            self::$PDO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }

        return self::$PDO;
    }

    /**
     * Destructeur
     */
    public function __destruct() {
        self::$PDO = null;
    }
}
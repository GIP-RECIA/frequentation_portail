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
        if (DB::$PDO === null) {
            $conf = (Config::getInstance())->get('db');
            DB::$PDO = new PDO(
                "mysql:host={$conf['host']};port={$conf['port']};dbname={$conf['dbName']};charset=utf8",
                $conf['user'],
                $conf['password']
            );
            DB::$PDO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }

        return DB::$PDO;
    }
}
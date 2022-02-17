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
     * Destructeur
     */
    public function __destruct() {
        self::$PDO = null;
    }

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
     * Génère une clause in pour une requête préparé
     *
     * @param string $field     Le nom du champ sur lequel se fait le in
     * @param array  $arrayElem Le tableau des éléments du in
     *
     * @return string La clause in sous forme de string paramétré
     */
    public static function generateInClause(string $field, array $arrayElem): string {
        return "{$field} IN (".str_repeat('?,', count($arrayElem) - 1) . "?)";
    }
    
    /**
     * Génère une clause in pour une requête préparé
     *
     * @param string $field     Le nom du champ sur lequel se fait le in
     * @param array  $arrayElem Le tableau des éléments du in
     * @param string $prefix    Le prefix du paramètre
     *
     * @return array En 0 la clause in sous forme de string paramétré et en 1 les arguments
     */
    public static function generateInClauseAndArgs(string $field, array $arrayElem, string $prefix = "p"): array {
        $res = "{$field} IN (";
        $first = true;
        $cpt = 0;
        $args = [];
    
        foreach($arrayElem as $elem) {
            if ($first) {
                $first = false;
            } else {
                $res .= ", ";
            }
    
            $key = $prefix.$cpt;
            $res .= ":{$key}";
            $cpt++;
            $args[$key] = $elem;
        }
    
        return [$res.")", $args];
    }
}
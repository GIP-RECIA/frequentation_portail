<?php

namespace App;

/**
 * Permet de récupérer la configuration
 */
class Config {
    private $settings = [];
    private static $_instance; // L'attribut qui stockera l'instance unique

    /**
     * La méthode statique qui permet d'instancier ou de récupérer l'instance unique
     **/
    public static function getInstance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new Config();
        }

        return self::$_instance;
    }

    /**
     * Le constructeur avec sa logique est privé pour empêcher l'instanciation en dehors de la classe
     **/
    private function __construct() {
        $this->settings = require('./include/config.php');
    }

    /**
     *  Permet d'obtenir la valeur de la configuration
     *
     *  @param $key string clef à récupérer
     *
     *  @return mixed
     **/
    public function get($key) {
        if (!isset($this->settings[$key])) {
            return null;
        }

        return $this->settings[$key];
    }
}
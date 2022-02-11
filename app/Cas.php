<?php

namespace App;

use Exception;
use App\Config;

/**
 * Permet de gérer le cas
 */
class Cas {
    private $config = null;
    private static $_instance; // L'attribut qui stockera l'instance unique

    /**
     * La méthode statique qui permet d'instancier ou de récupérer l'instance unique
     *
     * @return Cas L'instance Cas
     **/
    public static function getInstance(): Cas {
        if (is_null(self::$_instance)) {
            self::$_instance = new Cas();
        }

        return self::$_instance;
    }

    /**
     * Le constructeur avec sa logique est privé pour empêcher l'instanciation en dehors de la classe
     **/
    private function __construct() {
        $this->config = Config::getInstance();
    }

    /**
     * Initialisation de la connexion au CAS et force l'authentification
     */
    public function init(): void {
        if ($this->config->get('env') !== "dev") {
            $configCas = $this->config->get('cas');
            $cas_host = $configCas["host"];
            $cas_port = $configCas["port"];
            $cas_context = $configCas["context"];
            $cas_server_ca_cert_path = $configCas["certificat"];
            
            $cas_reals_hosts = [$cas_host];
            //si uniquement tranmission attribut
            phpCAS::setDebug(false);
            //phpCAS::setVerbose(true);
            
            phpCAS::client(CAS_VERSION_2_0, $cas_host, $cas_port, $cas_context);
            //phpCAS::client(SAML_VERSION_1_1, $cas_host, $cas_port, $cas_context);
            
            phpCAS::setCasServerCACert($cas_server_ca_cert_path);
            phpCAS::handleLogoutRequests(true, $cas_reals_hosts);
            phpCAS::forceAuthentication();
        }
    }

    /**
     * Lance la déconnexion du CAS
     */
    public function logout(): void {
        phpCAS::logout();
    }

    /**
     * Récupère un attribut CAS dans la session
     *
     * @param string $attributeName Le nom de l'attribut a récupérer
     *
     * @return string La valeur récupérée
     */
    public function getAttribute(string $attributeName): ?string {
        if ($this->config->get('env') === "dev") {
            return null;
        }

        return $_SESSION['phpCAS']['attributes'][$attributeName];
    }

    /**
     * Retourne l'identifiant de l'utilisateur courant
     *
     * @param string uid user
     */
    public function getUidUser(): string {
        if ($this->config->get('env') !== "dev") {
            return strtolower(phpCAS::getUser());
        }

        $debug = $this->config->get('debug');

        if ($debug !== null && array_key_exists('uid', $debug)) {
            return strtolower($debug['uid']);
        }

        throw new Exception("Impossible de récupérer l'uid de l'utilisateur");
    }
}
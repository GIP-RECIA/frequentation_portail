<?php

namespace App;

use Exception;
use App\Config;
use App\Cas;

class DroitsUtilisateur {
    /** @var LDAP\Connection|false */
    private $ldap = null;

    private const ATTR_A_RECUP_USER = ["ismemberof", "escouai"];
    private const ATTR_A_RECUP_STRUCT = ["entstructuresiren"];

    /** @var string Pour être admin lycée, il faut faire partie de ce groupe */
    private const GRP_ADMIN_LYCEE = 'esco:admin:Indicateurs:central';
    /** @var string Pour être admin des collèges d'un département, il faut faire partie de ce groupe */
    private const GRP_ADMIN_COLLEGE = 'clg%DEP%:admin:Indicateurs:central';

    /** @var string Le champ de l'uai */
    private const UAI = 'escouai';

    /** @var boolean  */
    private $isAdminLycee = false;

    /** @var array */
    private $isAdminCollege = [];

    /** @var array */
    private $tabSiren = [];

    // TODO: voir si les autres variables sont utiles a part le tabSiren
    /**
     * Constructeur
     **/
    public function __construct() {
        $config = Config::getInstance();
        session_start();

        if (!array_key_exists('tabSiren', $_SESSION) || $config->get('debug', 'disableCacheSession') === true) {
            $ldapConf = $config->get('ldap');
            $this->ldap = ldap_connect("{$ldapConf['protocole']}://{$ldapConf['host']}:{$ldapConf['port']}");
    
            if ($this->ldap === false) {
                throw new Exception("Unable to connect to ldap.");
            }
    
            ldap_set_option($this->ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_bind($this->ldap, $ldapConf['dn'], $ldapConf['password']);

            $this->generateListeSiren();

            $_SESSION['tabSiren'] = $this->tabSiren;
        } else {
            $this->tabSiren = $_SESSION['tabSiren'];
        }

    }

    /**
     * Destructeur
     */
    public function __destruct() {
        if ($this->ldap !== null) {
            ldap_close($this->ldap);
        }
    }

    public function getTabSiren(): array {
        return $this->tabSiren;
    }

    /**
     * Génère la liste des siren des établissement que l'utilisateur peut voir
     */
    private function generateListeSiren(): void {
        $config = Config::getInstance();
        $confLdap = $config->get('ldap');
        $uid = (Cas::getInstance())->getUidUser();
        $dn = 'ou=people,dc=esco-centre,dc=fr';
        $sr = ldap_search($this->ldap, $dn, "(uid={$uid})", self::ATTR_A_RECUP_USER);
        $userInfos = ldap_get_entries($this->ldap, $sr);
        $groupsAdminCollege = [];

        // Construction du tableau de la liste des groupes admin de collège a tester
        //  et affectation à false du fait d'être admin par un de ces groupes
        foreach ($config->get('departments') as $department) {
            $groupsAdminCollege[$department] = str_replace("%DEP%", $department, self::GRP_ADMIN_COLLEGE);
            $this->isAdminCollege[$department] = false;
        }

        if ($userInfos === false) {
            throw new Exception("Utilisateur \"{$uid}\" introuvable dans le ldap");
        }

        $nbGroupes = $userInfos[0]['ismemberof']['count'];

        // VERIFICATION SI ADMIN CENTRAL
        // parcours tous les groupes pour chercher si l'utilisateur possède des groupes admin pour l'application
        for ($i = 0; $i < $nbGroupes; $i++) {
            $groupe = $userInfos[0]['ismemberof'][$i];

            // Enregistre si l'utilisateur est admin pour les lycées
            if ($groupe === self::GRP_ADMIN_LYCEE) {
                $this->isAdminLycee = true;
            }

            // Enregistre si l'utilisateur est admin pour les collèges d'un département
            foreach ($groupsAdminCollege as $department => $grpAdminCollegeDep) {
                if ($groupe === $grpAdminCollegeDep) {
                    $this->isAdminCollege[$department] = true;
                }
            }
        }

        // Construction de la liste des uai e l'utilisateur
        // * Si utilisateur lambda : ses uai
        // * Si admin de groupe (lycée, collège d'un département), tous les établissement du groupe
        // * sinon erreur
        $dn_base = explode(",", $dn, 2);
        $dn = "ou=structures,{$dn_base[1]}";
        $this->addSirensIfAdmin($this->isAdminLycee,
            "(entstructuretypestruct=LYCEE *)", $dn, "Lycées non trouvés dans le ldap");

        foreach ($this->isAdminCollege as $department => $isAdmin) {
            $this->addSirensIfAdmin($isAdmin,
                "(entstructuretypestruct=COLLEGE)(entstructureuai=0{$department}*)", $dn,
                "Collèges pour le département {$department} non trouvés dans le ldap");
        }

        // Si l'utilisateur est liè a un ou des établissement, on les ajoutes
        if (isset($userInfos[0][self::UAI])) {
            $countUai = $userInfos[0][self::UAI]['count'];
            $uaiForUser = [];

            if ($countUai > 0) {
                for ($i = 0; $i < $countUai; $i++) {
                    $uaiForUser[] =$userInfos[0][self::UAI][$i];
                }
            } else {
                throw new Exception("Impossible de récupérer les uai liés à l'utilisateur");
            }

            foreach ($uaiForUser as $uaiEtab) {
                $filtre = "(entstructureuai={$uaiEtab})";
                $sr = ldap_search($this->ldap, $dn, $filtre, self::ATTR_A_RECUP_STRUCT);
                $etab = ldap_get_entries($this->ldap, $sr);

                if ($etab === false) {
                    throw new Exception("Impossible de trouver l'établissement {$uaiEtab} dans le ldap");
                }

                if ($etab[0]['entstructuresiren']['count'] === 0) {
                    throw new Exception("Impossible de récupérer le SIREN pour l'établissement {$uaiEtab}");
                }

                $this->addSiren($etab[0]['entstructuresiren'][0]);
            }
        }

        if (count($this->tabSiren) === 0) {
            throw new Exception("L'utilisateur ne peut voir aucun établissement");
        }
    }

    /**
     * Liste les siren en fonction d'un filtre si l'on est admin
     *
     * @param bool   $isAdmin      Permet de savoir si l'on est admin pour ce filtre
     * @param string $subFilter    Le filtre de recherche
     * @param string $dn
     * @param string $errorMessage Le message en cas d'erreur
     */
    private function addSirensIfAdmin(bool $isAdmin, string $subFilter, string $dn, string $errorMessage): void {
        if ($isAdmin) {
            $filter = "(&(objectclass=ENTEtablissement){$subFilter})";
            $sr = ldap_search($this->ldap, $dn, $filter, self::ATTR_A_RECUP_STRUCT);
            $etabs = ldap_get_entries($this->ldap, $sr);

            if ($etabs === false) {
                throw new Exception($errorMessage);
            }

            foreach ($etabs as $key => $etab) {
                if ($key !== "count" && $etab['entstructuresiren']['count'] > 0) {
                    $this->addSiren($etab['entstructuresiren'][0]);
                }
            }
        }
    }

    /**
     * Ajoute un siren a la liste des siren
     * 
     * @param string $siren Le siren a ajouter
     */
    private function addSiren(string $siren): void {
        if (!in_array($siren, $this->tabSiren, true)) {
            $this->tabSiren[] = $siren;
        }
    }
}
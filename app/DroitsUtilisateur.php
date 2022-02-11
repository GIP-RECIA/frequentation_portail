<?php

namespace App;

use Exception;
use App\Config;
use App\Cas;

// TODO: voir si on peux chopper les départements de l'utilisateur
class DroitsUtilisateur {
    /** @var LDAP\Connection|false */
    private $ldap = null;

    private const ATTR_A_RECUP_USER = ["ismemberof", "escouai"];
    private const ATTR_A_RECUP_STRUCT = ["entstructuresiren"];

    /** @var string Pour être admin lycée, il faut faire partie de ce groupe */
    private const GRP_ADMIN_LYCEE = 'esco:admin:Indicateurs:central';
    /** @var string Pour être admin des collèges d'un département, il faut faire partie de ce groupe */
    private const GRP_ADMIN_COLLEGE = 'clg%DEP%:admin:Indicateurs:central';

    /** @var boolean  */
    private $isAdminLycee = false;

    private $isAdminCollege = [];

    /**
     * Constructeur
     **/
    public function __construct() {
        $ldapConf = (Config::getInstance())->get('ldap');
        $this->ldap = ldap_connect("{$ldapConf['protocole']}://{$ldapConf['host']}:{$ldapConf['port']}");

        if ($this->ldap === false) {
            throw new Exception("Unable to connect to ldap.");
        }

        ldap_set_option($this->ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_bind($this->ldap, $ldapConf['dn'], $ldapConf['password']);
    }

    /**
     * Destructeur
     */
    public function __destruct() {
        if ($this->ldap !== null) {
            ldap_close($this->ldap);
        }
    }

    public function getUsersInfo(): array {
        $config = Config::getInstance();
        $confLdap = $config->get('ldap');
        $uid = (Cas::getInstance())->getUidUser();
        $dn = 'ou=people,dc=esco-centre,dc=fr';
        $sr = ldap_search($this->ldap, $dn, "(uid={$uid})", self::ATTR_A_RECUP_USER);
        $userInfos = ldap_get_entries($this->ldap, $sr);
        $groupsAdminCollege = [];

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
        // * Si admin central : pas de liste a construire, (tous les uai)
        // TODO: valider cette explication
        $dn_base = explode(",", $dn, 2);
        $dn = "ou=structures,{$dn_base[1]}";
        $tabSiren = [];

        foreach ($this->isAdminCollege as $department => $isAdmin) {
            if ($isAdmin) {
                $filtre = "(&(objectclass=ENTEtablissement)(entstructuretypestruct=COLLEGE)(entstructureuai=0{$department}*))";
                $sr = ldap_search($this->ldap, $dn, $filtre, self::ATTR_A_RECUP_STRUCT);
                $etabs = ldap_get_entries($this->ldap, $sr);

                if ($etabs == false) {
                    throw new Exception("Colléges pour le département {$department} non trouvés dans le ldap");
                }

                // TODO: pourquoi on n'a pas l'uai en même temps ?
                foreach ($etabs as $key => $etab) {
                    if ($key !== "count" && $etab['entstructuresiren']['count'] > 0) {
                        $tabSiren[] = $etab['entstructuresiren'][0];
                    }
                }
            }
        }
        
        return $userInfos;
    }
}
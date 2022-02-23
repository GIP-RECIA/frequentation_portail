<?php

namespace App;

use Exception;
use App\Config;
use App\Cas;
use App\NoEtabToDisplayException;

class DroitsUtilisateur {
    /** @var LDAP\Connection|false */
    private $ldap = null;

    private const ATTR_A_RECUP_USER = ["ismemberof", "escouai"];
    private const ATTR_A_RECUP_STRUCT = ["entstructuresiren"];

    /** @var string Pour être admin lycée, il faut faire partie de ce groupe */
    private const GRP_ADMIN_LYCEE = 'esco:admin:Indicateurs:central';
    /** @var string Pour être admin des collèges d'un département, il faut faire partie de ce groupe */
    private const GRP_ADMIN_COLLEGE = 'clg%DEP%:admin:Indicateurs:central';
    /** @var string Pour être admin cfa agricole, il faut faire partie de ce groupe */
    private const GRP_ADMIN_CFA_AGRI = 'agri:admin:Indicateurs:central';
    /** @var string Pour être admin cfa, il faut faire partie de ce groupe */
    private const GRP_ADMIN_CFA = 'cfa:admin:Indicateurs:central';
    /** @var string Pour être admin ef2s, il faut faire partie de ce groupe */
    private const GRP_ADMIN_EF2S = 'ef2s:admin:Indicateurs:central';

    /** @var string Le champ de l'uai */
    private const UAI = 'escouai';

    /** @var array */
    private $tabSiren = [];

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
        $filters = [];

        // Construction du tableau de la liste des groupes admin de collège a tester
        //  et affectation à false du fait d'être admin par un de ces groupes
        foreach ($config->get('departments') as $department) {
            $groupsAdminCollege[$department] = str_replace("%DEP%", $department, self::GRP_ADMIN_COLLEGE);
        }

        if ($userInfos === false) {
            throw new Exception("Utilisateur \"{$uid}\" introuvable dans le ldap");
        }

        $nbGroupes = $userInfos[0]['ismemberof']['count'];

        // VERIFICATION SI ADMIN CENTRAL
        // parcours tous les groupes pour chercher si l'utilisateur possède des groupes admin pour l'application
        for ($i = 0; $i < $nbGroupes; $i++) {
            $groupe = $userInfos[0]['ismemberof'][$i];

            // Enregistre si l'utilisateur est admin dans un des cas
            switch ($groupe) {
                case self::GRP_ADMIN_LYCEE:
                    $filters[] = "(entstructuretypestruct=LYCEE *)";
                    $filters[] = "(entstructuretypestruct=ETABLISSEMENT REGIONAL D'ENSEIGNT ADAPTE)";
                    break;
                case self::GRP_ADMIN_CFA_AGRI:
                    $filters[] = "(entstructuretypestruct=CFA AGRICOLE)";
                    break;
                case self::GRP_ADMIN_CFA:
                    $filters[] = "(entstructuretypestruct=CFA)";
                    break;
                case self::GRP_ADMIN_EF2S:
                    $filters[] = "(entstructuretypestruct=EF2S)";
                    $filters[] = "(entstructuretypestruct=UNIV)";
                    break;
            }

            // Enregistre si l'utilisateur est admin pour les collèges d'un département
            foreach ($groupsAdminCollege as $department => $grpAdminCollegeDep) {
                if ($groupe === $grpAdminCollegeDep) {
                    $filters[] = "(&(entstructuretypestruct=COLLEGE)(entstructureuai=0{$department}*))";
                }
            }
        }

        // Construction de la liste des uai de l'utilisateur
        // * Si utilisateur lambda : ses uai
        // * Si admin de groupe (lycée, collège d'un département, ...), tous les établissement du groupe
        // * sinon erreur
        $dn_base = explode(",", $dn, 2);
        $dn = "ou=structures,{$dn_base[1]}";

        // Si l'utilisateur est liè a un ou des établissement, on les ajoutes
        if (isset($userInfos[0][self::UAI])) {
            $countUai = $userInfos[0][self::UAI]['count'];

            if ($countUai === 0) {
                throw new Exception("Impossible de récupérer les uai liés à l'utilisateur");
            }

            for ($i = 0; $i < $countUai; $i++) {
                $filters[] = "(entstructureuai=".$userInfos[0][self::UAI][$i].")";
            }
        }

        // On joue la requête avec le filtre et on ajoute tous les siren
        if (count($filters) > 0) {
            $filter = "(&(objectclass=ENTEtablissement)(|".implode($filters)."))";
            $sr = ldap_search($this->ldap, $dn, $filter, self::ATTR_A_RECUP_STRUCT);
            $etabs = ldap_get_entries($this->ldap, $sr);

            if ($etabs === false) {
                throw new Exception("Impossible de trouver le ou les établissements dans le ldap avec le filtre ${$filter}");
            }

            foreach ($etabs as $key => $etab) {
                if ($key !== "count" && $etab['entstructuresiren']['count'] > 0) {
                    $siren = $etab['entstructuresiren'][0];
    
                    if (!in_array($siren, $this->tabSiren, true)) {
                        $this->tabSiren[] = $siren;
                    }
                }
            }
        }

        if (count($this->tabSiren) === 0) {
            throw new NoEtabToDisplayException("L'utilisateur {$uid} ne peut voir aucun établissement : {$filter}");
        }
    }
}
<?php

use App\Config;
use App\DB;
use App\DroitsUtilisateur;

/**
 * Retourne les données du tableau a afficher
 *
 * @param DroitsUtilisateur $droitsUtilisateur Les droits utilisateur
 * @param int               $etabId            L'identifiant de l'établissement sélectionné ou -1
 * @param bool              $serviceView       Un booléen pour savoir si l'on attends la vue service ou l'autre
 * @param int               $mois              L'identifiant du mois sur lequel on souhaite filtrer
 * @param array<int>        $departement       Les départements sur lesquels on souhaite filtrer, [] pour tous
 * @param array<int>        $etabType          Les types d'établissement sur lesquels on souhaite filtrer, [] pour tous
 * @param array<int>        $etabType2         Les type2s d'établissement sur lesquels on souhaite filtrer, [] pour tous
 * @param bool              $showSimpleData    Permet de savoir si il faut afficher les boutons top
 *
 * @return array Les données du tableau
 */
function getDataTable(
        DroitsUtilisateur $droitsUtilisateur, int $etabId, bool $serviceView, int $mois, array $departement,
        array $etabType, array $etabType2, bool $showSimpleData): array {
    $stats = getStats($droitsUtilisateur, $etabId, $serviceView, $mois, $departement, $etabType, $etabType2);
    $statsServices = $stats['statsServices'];
    $statsEtabs = $stats['statsEtabs'];
    $html = '';
    $table = [];

    foreach ($statsServices as $service) {
        if ($serviceView) {
            $statsEtab = $statsEtabs;
        } else {
            $statsEtab = $statsEtabs[$service['id']];
        }

        $line = array_merge($service, [
            'parent' => intval($service['parent__differents_users']),
            'eleve' => intval($service['eleve__differents_users']),
            'enseignant' => intval($service['enseignant__differents_users']),
            'persoEtabNonEns' => intval($service['perso_etab_non_ens__differents_users']),
            'persoCollec' => intval($service['perso_collec__differents_users']),
            'tuteurStage' => intval($service['tuteur_stage__differents_users']),
            'totalParent' => intval($statsEtab['parent__total_pers']),
            'totalEleve' => intval($statsEtab['eleve__total_pers']),
            'totalEnseignant' => intval($statsEtab['enseignant__total_pers']),
            'totalPersoEtabNonEns' => intval($statsEtab['perso_etab_non_ens__total_pers']),
            'totalPersoCollec' => intval($statsEtab['perso_collec__total_pers']),
            'totalTuteurStage' => intval($statsEtab['tuteur_stage__total_pers']),
        ]);

        if ($serviceView && !$showSimpleData) {
            $line['id'] = $service['id'];
        }

        $table[] = $line;
    }

    return $table;
}

/**
 * Génère les données de la popup top
 *
 * @param int        $idService   L'identifiant du service
 * @param int        $idMois      L'identifiant du mois
 * @param array<int> $departement Les départements sur lesquels on souhaite filtrer, [] pour tous
 * @param array<int> $etabType    Les types d'établissement sur lesquels on souhaite filtrer, [] pour tous
 * @param array<int> $etabType2   Les type2s d'établissement sur lesquels on souhaite filtrer, [] pour tous
 *
 * @return array Les données à afficher
 */
function getTopData(int $idService, int $idMois, array $departement, array $etabType, array $etabType2): array {
    $pdo = DB::getPdo();
    $where = [];
    $args = ['id_mois' => $idMois, 'id_service' => $idService];

    if ($departement !== []) {
        $res = DB::generateInClauseAndArgs("e.departement", $departement, 'dep');
        $where[] = $res[0];
        $args = array_merge($args, $res[1]);
    }

    if ($etabType !== []) {
        $res = DB::generateInClauseAndArgs("e.id_type", $etabType, "etun");
        $where[] = $res[0];
        $args = array_merge($args, $res[1]);
    }

    if ($etabType2 !== []) {
        $res = DB::generateInClauseAndArgs("e.id_type2", $etabType2, "etdeux");
        $where[] = $res[0];
        $args = array_merge($args, $res[1]);
    }

    if ($where !== []) {
        $where = 'AND '.implode(' AND ', $where);
    } else {
        $where = '';
    }

    $sql =
        "SELECT
            e.nom as nom,
            e.uai as uai,
            s.total_sessions as total,
            s.eleve__differents_users as eleves,
            s.enseignant__differents_users as enseignants,
            (s.perso_etab_non_ens__differents_users + s.perso_collec__differents_users) as autres,
            se.eleve__total_pers as totalEleves,
            se.enseignant__total_pers as totalEnseignants,
            (se.perso_etab_non_ens__total_pers + se.perso_collec__total_pers) as totalAutres
        FROM etablissements as e
        INNER JOIN stats_services as s ON e.id = s.id_etablissement
        INNER JOIN stats_etabs as se ON e.id = se.id_etablissement
        WHERE
            s.id_mois = :id_mois
            AND se.id_mois = :id_mois
            AND s.id_service = :id_service
            ${where}
        ORDER BY eleves/eleve__total_pers desc
        LIMIT 20";
    $req = $pdo->prepare($sql);
    $req->execute($args);

    return $req->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère l'établissement a partir de son SIREN
 *
 * @param int    $mois  L'identifiant du mois sur lequel on souhaite filtrer
 * @param string $siren Le siren de l'établissement
 *
 * @param int L'id de l'établissement
 *
 * @param Exception Si on ne trouve pas l'établissement
 */
function get_etablissement_id_by_siren(int $mois, string $siren): int {
    $pdo = DB::getPdo();
    $req = $pdo->prepare("
        SELECT e.id as id
        FROM etablissements as e
        INNER JOIN stats_etabs as se ON se.id_etablissement = e.id
        WHERE se.id_mois = :id_mois AND  e.siren = :siren");
    $req->execute(['id_mois' => $mois, 'siren' => $siren]);

    if ($row = $req->fetch(PDO::FETCH_ASSOC)) {
        return intval($row['id']);
    }

    throw new Exception("Impossible de trouver l'établissement ayant pour siren {$siren} lors du mois ayant l'id {$mois}.");
}

/**
 * Récupère les statistiques des différents services d'une établissement ou des différents établissement
 *
 * @param DroitsUtilisateur $droitsUtilisateur Les droits utilisateur
 * @param int               $etabId            L'identifiant de l'établissement sélectionné ou -1
 * @param bool              $serviceView       Un booléen pour savoir si l'on attends la vue service ou l'autre
 * @param int               $mois              L'identifiant du mois sur lequel on souhaite filtrer
 * @param array<int>        $departement       Les départements sur lesquels on souhaite filtrer, [] pour tous
 * @param array<int>        $etabType          Les types d'établissement sur lesquels on souhaite filtrer, [] pour tous
 * @param array<int>        $etabType2         Les type2s d'établissement sur lesquels on souhaite filtrer, [] pour tous
 *
 * @return array Le tableau des résultats
 */
function getStats(
        DroitsUtilisateur $droitsUtilisateur, int $etabId, bool $serviceView, int $mois, array $departement,
        array $etabType, array $etabType2): array {
    $pdo = DB::getPdo();
    $where = ["id_mois = :id_mois"];
    $statsServices = [];
    $statsEtabs = [];
    $join = "";
    $from = "";
    $select2 = "";
    $groupBy2 = "";
    $args = ['id_mois' => $mois];

    if ($etabId !== -1) {
        $where[] = "id_etablissement = :id_etablissement";
        $args['id_etablissement'] = $etabId;
    }

    if ($departement !== []) {
        $res = DB::generateInClauseAndArgs("%alias%.departement", $departement, 'dep');
        $where[] = $res[0];
        $args = array_merge($args, $res[1]);
    }

    if ($etabType !== []) {
        $res = DB::generateInClauseAndArgs("%alias%.id_type", $etabType, "etun");
        $where[] = $res[0];
        $args = array_merge($args, $res[1]);
    }

    if ($etabType2 !== []) {
        $res = DB::generateInClauseAndArgs("%alias%.id_type2", $etabType2, "etdeux");
        $where[] = $res[0];
        $args = array_merge($args, $res[1]);
    }

    $res = DB::generateInClauseAndArgs("%alias%.siren", $droitsUtilisateur->getTabSiren(), "siren");
    $where[] = $res[0];
    $args = array_merge($args, $res[1]);

    $where = implode(' AND ', $where);

    if ($where !== '') {
        $where = " WHERE {$where}";
    }

    if ($serviceView) {
        $select1 = "'' as uai,";
        $from = "FROM stats_services as s";
        $join = 
            "INNER JOIN services as e ON e.id = s.id_service
            INNER JOIN etablissements as etab ON etab.id = s.id_etablissement";
        $alias = "etab";
    } else {
        $select1 = "e.uai as uai,";
        $from = "FROM stats_etabs as s";
        $join = "INNER JOIN etablissements as e ON e.id = s.id_etablissement";
        $alias = "e";
        $select2 = "s.id_etablissement as id,";
        $groupBy2 = "GROUP BY id_etablissement";
    }

    $where1 = str_replace("%alias%", $alias, $where);
    $where2 = str_replace("%alias%", "etab", $where);

    $sql =
        "SELECT
            e.id as id,
            e.nom as nom,
            {$select1}
            SUM(s.au_plus_quatre_fois) as au_plus_quatre_fois,
            SUM(s.au_moins_cinq_fois) as au_moins_cinq_fois,
            SUM(s.differents_users) as differents_users,
            SUM(s.total_sessions) as total_sessions,
            SUM(s.parent__differents_users) as parent__differents_users,
            SUM(s.eleve__differents_users) as eleve__differents_users,
            SUM(s.enseignant__differents_users) as enseignant__differents_users,
            SUM(s.perso_etab_non_ens__differents_users) as perso_etab_non_ens__differents_users,
            SUM(s.perso_collec__differents_users) as perso_collec__differents_users,
            SUM(s.tuteur_stage__differents_users) as tuteur_stage__differents_users
        {$from}
        {$join}
        {$where1}
        GROUP BY e.id
        ORDER BY e.nom";

    $req = $pdo->prepare($sql);
    $req->execute($args);
    $statsServices = $req->fetchAll(PDO::FETCH_ASSOC);

    $sql =
        "SELECT
            {$select2}
            SUM(s.parent__total_pers) as parent__total_pers,
            SUM(s.eleve__total_pers) as eleve__total_pers,
            SUM(s.enseignant__total_pers) as enseignant__total_pers,
            SUM(s.perso_etab_non_ens__total_pers) as perso_etab_non_ens__total_pers,
            SUM(s.perso_collec__total_pers) as perso_collec__total_pers,
            SUM(s.tuteur_stage__total_pers) as tuteur_stage__total_pers
        FROM stats_etabs as s
        INNER JOIN etablissements as etab ON etab.id = s.id_etablissement
        {$where2}
        {$groupBy2}";

    $req = $pdo->prepare($sql);
    $req->execute($args);

    if ($serviceView) {
        $row = $req->fetch(PDO::FETCH_ASSOC);
        $statsEtabs = $row;
    } else {
        while ($row = $req->fetch(PDO::FETCH_ASSOC)) {
            $statsEtabs[$row['id']] = $row;
        }
    }

    return ['statsServices' => $statsServices, 'statsEtabs' => $statsEtabs];
}

/**
 * Génère la liste des mois ordonnées et bien formaté
 *
 * @param string $siren Le siren de l'établissement dont on souhaite afficher les mois, si présent
 *
 * @return array<string> La liste des mois
 */
function getListMois(string $siren = null): array {
    $pdo = DB::getPdo();
    $sql = "";

    if (empty($siren)) {
        $sql = "SELECT id, concat(LPAD(mois,2,'0'), ' / ', annee) as mois FROM mois ORDER BY annee DESC, mois DESC";
        $data = [];
    } else {
        $sql = "
            SELECT m.id as id, concat(LPAD(mois,2,'0'), ' / ', annee) as mois
            FROM mois as m
            INNER JOIN stats_etabs as se ON se.id_mois = m.id
            INNER JOIN etablissements as e ON e.id = se.id_etablissement
            WHERE e.siren = :siren
            ORDER BY m.annee DESC, m.mois DESC";
        $data = ['siren' => $siren];
    }

    $req = $pdo->prepare($sql);
    $req->execute($data);

    return $req->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Retourne la liste des département en fonction du mois
 *
 * @param DroitsUtilisateur $droitsUtilisateur Les droits utilisateur
 * @param int               $mois              L'identifiant du mois
 *
 * @return array<int> Un tableau des départements
 */
function getDepartements(DroitsUtilisateur $droitsUtilisateur, int $mois): array {
    $pdo = DB::getPdo();
    /**
     * Converti l'élément "departement" du tableau en entier
     *
     * @param array $arr Le tableau a convertir
     *
     * @return int Le département résultat
     */
    $func = function(array $arr): int {
        return intval($arr['departement']);
    };
    
    $res = generateWhereInFilters($droitsUtilisateur, $mois);
    
    $req = $pdo->prepare("
        SELECT e.departement as departement
        FROM etablissements as e
        INNER JOIN stats_etabs as se ON se.id_etablissement = e.id
        WHERE {$res[0]}
        GROUP BY e.departement
        ORDER BY e.departement");
    $req->execute($res[1]);

    return array_map($func, $req->fetchAll(PDO::FETCH_ASSOC));
}

/**
 * Retourne la liste des types d'établissement
 *
 * @param DroitsUtilisateur $droitsUtilisateur Les droits utilisateur
 * @param int               $mois              L'identifiant du mois
 * @param array<int>        $departements      Les départements dont on souhaite les établissements
 *
 * @return array<string> Un tableau de types d'établissements
 */
function getTypesEtablissements(DroitsUtilisateur $droitsUtilisateur, int $mois, array $departements): array {
    $pdo = DB::getPdo();
    
    $res = generateWhereInFilters($droitsUtilisateur, $mois, $departements);

    // On ne récupère que les types d'établissement du mois actuel
    //  au cas où il y'aurait eu des types différents lors d'un autre mois
    $req = $pdo->prepare("
        SELECT t.id as id, t.nom as nom
        FROM types as t
        INNER JOIN etablissements as e ON e.id_type = t.id
        INNER JOIN stats_etabs as se ON se.id_etablissement = e.id
        WHERE {$res[0]}
        GROUP BY t.id
        ORDER BY t.nom asc");
    $req->execute($res[1]);

    return $req->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Retourne la liste des types2 d'établissement
 *
 * @param DroitsUtilisateur $droitsUtilisateur Les droits utilisateur
 * @param int               $mois              L'identifiant du mois
 * @param array<int>        $departements      Les départements dont on souhaite les établissements
 * @param array<int>        $etabTypes         Les types d'établissement à retourner
 *
 * @return array<string> Un tableau de types2 d'établissements
 */
function getTypes2Etablissements(DroitsUtilisateur $droitsUtilisateur, int $mois, array $departements, array $etabTypes): array {
    $pdo = DB::getPdo();
    $res = generateWhereInFilters($droitsUtilisateur, $mois, $departements, $etabTypes);

    // On ne récupère que les types2 d'établissement du mois actuel et des types actuel
    //  au cas où il y'aurait eu des types2 différents lors d'un autre mois
    $req = $pdo->prepare("
        SELECT t.id as id, t.nom as nom
        FROM types2 as t
        INNER JOIN etablissements as e ON e.id_type2 = t.id
        INNER JOIN stats_etabs as se ON se.id_etablissement = e.id
        WHERE {$res[0]}
        GROUP BY t.id
        ORDER BY t.nom asc");
    $req->execute($res[1]);

    return $req->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Retourne la liste des établissements en fonction du/des types d'établissement si ils sont présents
 *
 * @param DroitsUtilisateur $droitsUtilisateur Les droits utilisateur
 * @param int               $mois              L'identifiant du mois
 * @param array<int>        $departements      Les départements dont on souhaite les établissements
 * @param array<int>        $etabTypes         Les types d'établissement à retourner
 * @param array<int>        $etabTypes2        Les types d'établissement avancé à retourner
 *
 * @return array<id, string> Un tableau d'établissements
 */
function getEtablissements(DroitsUtilisateur $droitsUtilisateur, int $mois, array $departements, array $etabTypes, array $etabTypes2): array {
    $pdo = DB::getPdo();
    $res = generateWhereInFilters($droitsUtilisateur, $mois, $departements, $etabTypes, $etabTypes2);

    /**
     * Converti l'élément "id" du tableau en entier
     *
     * @param array $arr Le tableau a convertir
     *
     * @return array Le tableau résultat
     */
    $func = function(array $arr): array {
        $arr['id'] = intval($arr['id']);

        return $arr;
    };
    
    $req = $pdo->prepare("
        SELECT e.id as id, CONCAT(IFNULL(e.uai, '?'), ' - ', e.nom) as nom
        FROM etablissements as e
        INNER JOIN stats_etabs as se ON se.id_etablissement = e.id
        WHERE {$res[0]}
        ORDER BY e.nom");
    $req->execute($res[1]);

    return array_map($func, $req->fetchAll(PDO::FETCH_ASSOC));
}

/**
 * Retourne la clause where et les arguments des filtres
 *
 * @param DroitsUtilisateur $droitsUtilisateur Les droits utilisateur
 * @param int               $mois              L'identifiant du mois
 * @param array<int>        $departements      Les départements
 * @param array<int>        $etabTypes         Les types d'établissement
 * @param array<int>        $etabTypes2        Les types d'établissement avancé
 *
 * @return array<id, string> Un tableau d'établissements
 */
function generateWhereInFilters(DroitsUtilisateur $droitsUtilisateur, int $mois, array $departements = null, array $etabTypes = null, array $etabTypes2 = null): array {
    $where = "se.id_mois = :id_mois";
    $args = ['id_mois' => $mois];

    if ($departements !== null && count($departements) !== 0) {
        $res = DB::generateInClauseAndArgs("e.departement", $departements, "dep");
        $where .= " AND ".$res[0];
        $args = array_merge($args, $res[1]);
    }

    if ($etabTypes !== null && count($etabTypes) !== 0) {
        $res = DB::generateInClauseAndArgs("e.id_type", $etabTypes, "et1");
        $where .= " AND ".$res[0];
        $args = array_merge($args, $res[1]);
    }

    if ($etabTypes2 !== null && count($etabTypes2) !== 0) {
        $res = DB::generateInClauseAndArgs("e.id_type2", $etabTypes2, "et2");
        $where .= " AND ".$res[0];
        $args = array_merge($args, $res[1]);
    }

    $res = DB::generateInClauseAndArgs("e.siren", $droitsUtilisateur->getTabSiren(), "siren");
    $where .= " AND ".$res[0];
    $args = array_merge($args, $res[1]);

    return [$where, $args];
}

/**
 * Affiche une exception comme il faut en fonction de l'env
 *
 * @param Exception $e L'exception à afficher
 */
function showException(Exception $e): void {
    http_response_code(500);
    echo "Server Error";
    $env = (Config::getInstance())->get('env');

    // Si on est pas en prod, on affiche l'erreur, sinon on la log
    if ($env !== null && $env !== "prod") {
        $trace = $e->getTrace();
        echo "<br>".$e->getMessage();
        echo "<br>Fichier : ".$trace[0]['file'].":".$trace[0]['line'];
    } else {
        error_log($e->getMessage());
    }
}

/**
 * Effectue un rendue twig
 *
 * @param string $template Le nom du fichier template
 * @param array  $data     Le tableau des données a insérer dans le template
 *
 * @return string Les données générées
 */
function renderTwig(string $template, array $data = []): string {
    $config = Config::getInstance();
    $params = [];

    if ($config->get('cacheDir') !== null && $config->get('env') === 'prod') {
        $params['cache'] = $config->get('cacheDir');
    }

    // le dossier ou on trouve les templates
    $loader = new Twig\Loader\FilesystemLoader('templates');
    // initialiser l'environnement Twig
    $twig = new Twig\Environment($loader, $params);
    // load template
    $template = $twig->load($template);
    // set template variables
    // render template
    echo $template->render($data);
}  
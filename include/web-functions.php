<?php

/**
 * Retourne les données du tableau a afficher
 *
 * @param Object        $pdo            L'objet pdo
 * @param string        $etabId         L'identifiant de l'établissement sélectionné ou "-1"
 * @param string        $resultType     Le type de vue attendu, soit VIEW_SERVICES, soit VIEW_ETABS
 * @param array<string> $etabType       Les types d'établissement sur lesquels on souhaite filtrer, "-1" pour tous
 * @param string        $mois           Le mois sur lequel on souhaite filtrer, "-1" pour tous
 * @param bool          $showSimpleData Permet de savoir si il faut afficher les boutons top
 *
 * @return string Le code html du tableau
 */
function displayTable(Object &$pdo, string $etabId, string $resultType, array $etabType, string $mois, bool $showSimpleData) {
    $serviceView = $resultType === VIEW_SERVICES;
    $stats = getStats($pdo, $etabId, $resultType, $etabType, $mois);
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
            'totalParent' => intval($statsEtab['parent__differents_users']),
            'totalEleve' => intval($statsEtab['eleve__differents_users']),
            'totalEnseignant' => intval($statsEtab['enseignant__differents_users']),
            'totalPersoEtabNonEns' => intval($statsEtab['perso_etab_non_ens__differents_users']),
            'totalPersoCollec' => intval($statsEtab['perso_collec__differents_users']),
            'totalTuteurStage' => intval($statsEtab['tuteur_stage__differents_users']),
        ]);

        if ($serviceView && !$showSimpleData) {
            $line['id'] = $service['id'];
        }

        $table[] = $line;
    }

    return $table;
}

/**
 * Génère la popup top
 *
 * @param Object $pdo       L'objet pdo
 * @param string $serviceId L'identifiant du service
 * @param string $mois      Le mois ou "-1" si tous les mois
 */
function getTopHTML(Object &$pdo, string $serviceId, string $mois) {
    $etabs = [];
    $stats = [];
    $html = '<table id="top20Desc" class="topResult">';
    $html .= "<tr><td>Établissement</td><td>Total</td><td>Élèves</td><td>Enseignants</td><td>Autres</td></tr>";
    $intServiceId = intval($serviceId);
    $where = "";

    /*if ($mois !== "-1") {
        $where = generateWhereMonth($mois)." AND";
    }*/

    $sql =
        "SELECT
            e.id,
            e.nom,
            SUM(s.total_sessions) as total,
            s.differents_users,
            CEIL(AVG(s.eleve__differents_users)) as eleve,
            CEIL(AVG(s.enseignant__differents_users)) as enseignant,
            CEIL(AVG(s.perso_etab_non_ens__differents_users)) as perso_etab_non_ens,
            CEIL(AVG(s.perso_collec__differents_users)) as perso_collec,
            se.eleve__differents_users,
            se.enseignant__differents_users,
            se.perso_etab_non_ens__differents_users,
            se.perso_collec__differents_users
        FROM etablissements as e
        INNER JOIN stats_services as s ON e.id = s.id_lycee
        INNER JOIN stats_etabs as se ON e.id = se.id_lycee
        WHERE
            {$where}
            s.id_service = :id_service
        GROUP BY e.id
        ORDER BY total desc
        LIMIT 20";
    $args = ['id_service' => $intServiceId];
    $req = $pdo->prepare($sql);
    $req->execute($args);

    while ($row = $req->fetch()) {
        $eleves = 0;
        $enseignants = 0;
        $autres = 0;
        $total_eleves = intval($row['eleve__differents_users']);
        $total_enseignants = intval($row['enseignant__differents_users']);
        $total_autres = intval($row['perso_etab_non_ens__differents_users']) + intval($row['perso_collec__differents_users']);

        if ($total_eleves !== 0) {
            $eleves = "".round(intval($row['eleve']) / $total_eleves * 100, 2)."%";
        }

        if ($total_enseignants !== 0) {
            $enseignants = "".round(intval($row['enseignant']) / $total_enseignants * 100, 2)."%";
        }

        if ($total_autres !== 0) {
            $autres = "".round((intval($row['perso_etab_non_ens']) + intval($row['perso_collec'])) / $total_autres * 100, 2)."%";
        }

        $html .= "<tr><td>{$row['nom']}</td><td>{$row['total']}</td><td>{$eleves}</td><td>{$enseignants}</td><td>{$autres}</td></tr>";
    }

    return $html."</table>";
}

/**
 * Récupère l'établissement a partir de son SIREN
 *
 * @param Object $pdo   L'objet pdo
 * @param string $siren Le siren de l'établissement
 *
 * @param string L'id de l'établissement ou false si n on trouvé
 */
function get_etablissement_id_by_siren(Object &$pdo, string $siren) {
    $req = $pdo->prepare("SELECT id from etablissements where siren = :siren");
    $req->execute(["siren" => $siren]);

    if ($row = $req->fetch()) {
        return $row['id'];
    }

    return false;
}

/**
 * Récupère les statistiques des différents services d'une établissement ou des différents établissement
 *
 * @param Object        $pdo            L'objet pdo
 * @param string        $etabId         L'identifiant de l'établissement sélectionné ou "-1"
 * @param string        $resultType     Le type de vue attendu, soit VIEW_SERVICES, soit VIEW_ETABS
 * @param array<string> $etabType       Les types d'établissement sur lesquels on souhaite filtrer, "-1" pour tous
 * @param string        $mois           Le mois sur lequel on souhaite filtrer, "-1" pour tous
 *
 * @return array Le tableau des résultats
 */
function getStats(Object &$pdo, string $etabId, string $resultType, array $etabType, string $mois) {
    $serviceView = $resultType === VIEW_SERVICES;
    $where = [];
    $statsServices = [];
    $statsEtabs = [];
    $join = "";
    $from = "";
    $select2 = "";
    $groupBy2 = "";
    $args = [];

    if ($etabId !== '-1') {
        $where[] = "id_lycee = :id_lycee";
        $args['id_lycee'] = $etabId;
    }

    if ($mois !== '-1') {
        $r = explode(' / ', $mois);
        $localMois = $r[0];
        $localAnnee = $r[1];
        $where[] = "mois = :mois and annee = :annee";
        $args = array_merge($args, ['mois' => $localMois, 'annee' => $localAnnee]);
    }

    if (count($etabType) !== 0) {
        $res = generateInClauseAndArgs("%alias%.type", $etabType);
        $where[] = $res[0];
        $args = array_merge($args, $res[1]);
    }

    $where = implode(' AND ', $where);

    if ($where !== '') {
        $where = " WHERE {$where}";
    }

    if ($serviceView) {
        $join = 
            "INNER JOIN services as e ON e.id = s.id_service
            INNER JOIN etablissements as etab ON etab.id = s.id_lycee";
        $from = "FROM stats_services as s";
        $alias = "etab";
    } else {
        $join = "INNER JOIN etablissements as e ON e.id = s.id_lycee";
        $from = "FROM stats_etabs as s";
        $alias = "e";
        $select2 = "s.id_lycee as id,";
        $groupBy2 = "GROUP BY id_lycee";
    }

    $where1 = str_replace("%alias%", $alias, $where);
    $where2 = str_replace("%alias%", "etab", $where);

    $sql =
        "SELECT
            e.id as id,
            e.nom as nom,
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

    while ($row = $req->fetch()) {
        $statsServices[] = $row;
    }

    $sql =
        "SELECT
            {$select2}
            SUM(s.parent__differents_users) as parent__differents_users,
            SUM(s.eleve__differents_users) as eleve__differents_users,
            SUM(s.enseignant__differents_users) as enseignant__differents_users,
            SUM(s.perso_etab_non_ens__differents_users) as perso_etab_non_ens__differents_users,
            SUM(s.perso_collec__differents_users) as perso_collec__differents_users,
            SUM(s.tuteur_stage__differents_users) as tuteur_stage__differents_users
        FROM stats_etabs as s
        INNER JOIN etablissements as etab ON etab.id = s.id_lycee
        {$where2}
        {$groupBy2}";

    $req = $pdo->prepare($sql);
    $req->execute($args);

    if ($serviceView) {
        $row = $req->fetch();
        $statsEtabs = $row;
    } else {
        while ($row = $req->fetch()) {
            $statsEtabs[$row['id']] = $row;
        }
    }

    return ['statsServices' => $statsServices, 'statsEtabs' => $statsEtabs];
}

/**
 * Retourne la liste des types d'établissement
 *
 * @param Object $pdo L'objet pdo
 *
 * @return array<string> Un tableau de types d'établissements
 */
function getTypesEtablissements(Object &$pdo)
{
    $req = $pdo->prepare("SELECT distinct(type) as t FROM etablissements order by t asc");
    $req->execute();
    $types = [];

    while ($row = $req->fetch()) {
        $types[] = $row['t'];
    }

    return $types;
}

/**
 * Retourne la liste des établissements en fonction du/des types d'établissement si ils sont présents
 *
 * @param Object $pdo L'objet pdo
 * @param array<string> $etabTypes Les types d'établissement à retourner
 *
 * @return array<string, string> Un tableau d'établissements
 */
function getEtablissements(Object &$pdo, array $etabTypes) {
    $etabs = [];
    $where = "";

    if (count($etabTypes) !== 0) {
        $where = "WHERE ".generateInClause("type", $etabTypes);
    }
    
    $req = $pdo->prepare("SELECT * FROM etablissements {$where}");
    $req->execute($etabTypes);

    while ($row = $req->fetch()) {
        $etabs[$row['id']] = $row['nom'];
    }

    return $etabs;
}

/**
 * Génère la liste des mois ordonnées et bien formaté
 *
 * @param Object $pdo L'objet pdo
 *
 * @return array<string> La liste des mois
 */
function getListMois(Object &$pdo) {
    $req = $pdo->prepare("SELECT DISTINCT(concat(LPAD(mois,2,'0'), ' / ', annee)) as m FROM stats_etabs ORDER BY annee DESC, m DESC");
    $req->execute();
    $list = [];

    while ($row = $req->fetch()) {
        $list[] = $row['m'];
    }

    return $list;
}

/**
 * Génère une clause in pour une requête préparé
 *
 * @param string $field     Le nom du champ sur lequel se fait le in
 * @param array  $arrayElem Le tableau des éléments du in
 *
 * @return string La clause in sous forme de string paramétré
 */
function generateInClause(string $field, array $arrayElem) {
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
function generateInClauseAndArgs(string $field, array $arrayElem, string $prefix = "p") {
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
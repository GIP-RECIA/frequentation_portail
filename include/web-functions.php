<?php
function getDbParam() {
    $config = realpath('./config.ini');
    $data = parse_ini_file($config, true)['db'];
    return [
        'user_db' => $data['user_db'],
        'server' => $data['server'],
        'user_pwd' => $data['user_pwd'],
        'table_name' => $data['table_name'],
    ];
}
function getAnnuaireParam() {
    $config = realpath('./config.ini');
    $data = parse_ini_file($config, true)['annuaire'];
    return [
        'host' => $data['host'],
        'port' => $data['port'],
        'context' => $data['context'],
        'certificat' => $data['certificat'],
    ];
}

function nice($data) {
    echo "<pre>" . print_r($data, true) . "</pre>";
}

function displayTable($etabId) {
    global $resultType;

    $html = '<div class="table-responsive"><table id="result" class="table table-sm table-striped population">';

    $resultLabel = "";

    if ($resultType == 'services') {
        $resultLabel = "Service";
    } else {
        $resultLabel = "Etablissement";
    }

    $html .= '<thead class="thead-dark">';
    $html .= '<tr>';
    $html .= '<th></th>';
    $html .= '<th colspan="4">Visiteurs - Visites</th>';
    $html .= '<th colspan="6" class="population-elem">Populations</th>';
    $html .= '<th colspan="6" class="ratio-elem">Ratio par rapport aux utilisateurs potentiels</th>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<th>' . $resultLabel . '</th>';
    $html .= '<th>Au plus quatre fois</th>';
    $html .= '<th>Au moins cinq fois</th>';
    $html .= '<th>Nb. visiteurs</th>';
    $html .= '<th>Total visites</th>';
    $html .= '<th class="population-elem">Parent</th>';
    $html .= '<th class="population-elem">Élève</th>';
    $html .= '<th class="population-elem">Enseignant</th>';
    $html .= '<th class="population-elem">Personnel d\'établissement non enseignant</th>';
    $html .= '<th class="population-elem">Personnel de collectivité</th>';
    $html .= '<th class="population-elem">Tuteur de stage</th>';
    $html .= '<th class="ratio-elem">Parent</th>';
    $html .= '<th class="ratio-elem">Élève</th>';
    $html .= '<th class="ratio-elem">Enseignant</th>';
    $html .= '<th class="ratio-elem">Personnel d\'établissement non enseignant</th>';
    $html .= '<th class="ratio-elem">Personnel de collectivité</th>';
    $html .= '<th class="ratio-elem">Tuteur de stage</th>';
    $html .= '</tr>';
    $html .= '</thead>';
    $html .= '<tbody>';
    $html .= getStatsHTML($etabId);
    $html .= '</tbody>';
    $html .= '</table>';
    $html .= '</div>';

    return $html;
}

function getTopHTML($serviceId, $month) {
    global $conn;
    $etabs = [];
    $stats = [];
    $html = '<table id="top20Desc" class="topResult">';
    $html .= "<tr><td>Établissement</td><td>Total</td><td>Élèves</td><td>Enseignants</td><td>Autres</td></tr>";
    $intServiceId = intval($serviceId);
    $where = "";

    /*if ($month !== "-1") {
        $where = generateWhereMonth($month)." AND";
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
            s.id_service = {$intServiceId}
        GROUP BY e.id
        ORDER BY total desc
        LIMIT 20";

    if ($res = $conn->query($sql)) {
        while ($row = $res->fetch_array()) {
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
                $autres = "".round((intval($row['personnel_etablissement_non_enseignant']) + intval($row['personnel_collectivite'])) / $total_autres * 100, 2)."%";
            }

            $html .= "<tr><td>{$row['nom']}</td><td>{$row['total']}</td><td>{$eleves}</td><td>{$enseignants}</td><td>{$autres}</td></tr>";
        }

        $res->free_result();
    }

    return $html."</table>";
}

function get_etablissement_id_by_siren($siren) {
    global $conn;
    $sql = "SELECT * from etablissements where siren = '{$siren}'";
    $query = $conn->query($sql);
    $result = $query->fetch_assoc();

    if (isset($result['id'])) {
        return $result['id'];
    }

    return false;
}

/**
 * Retourne le code html du tableau de la liste des services/établissements
 *
 * @param string $etabId
 */
function getStatsHTML($etabId) {
    global $etab, $resultType, $show_simple_data;

    $serviceView = $resultType == 'services';
    $stats = getStats($etabId);
    $statsServices = $stats['statsServices'];
    $statsEtabs = $stats['statsEtabs'];
    $html = '';

    foreach ($statsServices as $service) {
        if ($serviceView) {
            $statsEtab = $statsEtabs;
        } else {
            $statsEtab = $statsEtabs[$service['id']];
        }

        $parent = intval($service['parent__differents_users']);
        $eleve = intval($service['eleve__differents_users']);
        $enseignant = intval($service['enseignant__differents_users']);
        $perso_etab_non_ens = intval($service['perso_etab_non_ens__differents_users']);
        $perso_collec = intval($service['perso_collec__differents_users']);
        $tuteur_stage = intval($service['tuteur_stage__differents_users']);
        $total_parent = intval($statsEtab['parent__differents_users']);
        $total_eleve = intval($statsEtab['eleve__differents_users']);
        $total_enseignant = intval($statsEtab['enseignant__differents_users']);
        $total_perso_etab_non_ens = intval($statsEtab['perso_etab_non_ens__differents_users']);
        $total_perso_collec = intval($statsEtab['perso_collec__differents_users']);
        $total_tuteur_stage = intval($statsEtab['tuteur_stage__differents_users']);
        $top = "";

        if ($serviceView && !$show_simple_data) {
            $top = "<span class=\"top20\" data-serviceid=\"{$service['id']}\" class=\"float-right\">TOP</span>";
        }

        $html .= "<tr>";

        $html .= "<td>{$top}{$service['nom']}</td>";
        $html .= "<td>{$service['au_plus_quatre_fois']}</td>";
        $html .= "<td>{$service['au_moins_cinq_fois']}</td>";
        $html .= "<td>{$service['differents_users']}</td>";
        $html .= "<td>{$service['total_sessions']}</td>";

        $html .= "<td class=\"population-elem\">{$parent}</td>";
        $html .= "<td class=\"population-elem\">{$eleve}</td>";
        $html .= "<td class=\"population-elem\">{$enseignant}</td>";
        $html .= "<td class=\"population-elem\">{$perso_etab_non_ens}</td>";
        $html .= "<td class=\"population-elem\">{$perso_collec}</td>";
        $html .= "<td class=\"population-elem\">{$tuteur_stage}</td>";

        $html .= "<td class=\"ratio-elem\">".lineRatio($total_parent, $parent)."</td>";
        $html .= "<td class=\"ratio-elem\">".lineRatio($total_eleve, $eleve)."</td>";
        $html .= "<td class=\"ratio-elem\">".lineRatio($total_enseignant, $enseignant)."</td>";
        $html .= "<td class=\"ratio-elem\">".lineRatio($total_perso_etab_non_ens, $perso_etab_non_ens)."</td>";
        $html .= "<td class=\"ratio-elem\">".lineRatio($total_perso_collec, $perso_collec)."</td>";
        $html .= "<td class=\"ratio-elem\">".lineRatio($total_tuteur_stage, $tuteur_stage)."</td>";

        $html .= "</tr>";
    }

    return $html;
}

/**
 * Récupère les statistiques des différents services d'une établissement ou des différents établissement
 *
 * @param string $etab L'identifiant de l'établissement
 */
function getStats($etab) {
    global $conn, $resultType, $mois;

    $serviceView = $resultType == 'services';
    $where = [];
    $statsServices = [];
    $statsEtabs = [];
    $join = "";
    $from = "";
    $select2 = "";
    $groupBy2 = "";

    if ($etab !== '-1') {
        $where[] = "id_lycee = {$etab}";
    }

    if ($mois !== '-1') {
        $where[] = generateWhereMonth($mois);
    }

    $where = implode(' AND ', $where);

    if ($where !== '') {
        $where = " WHERE {$where}";
    }

    if ($serviceView) {
        $join = "INNER JOIN services as e ON e.id = s.id_service";
        $from = "FROM stats_services as s";
    } else {
        $join = "INNER JOIN etablissements as e ON e.id = s.id_lycee";
        $from = "FROM stats_etabs as s";
        $select2 = "id_lycee as id,";
        $groupBy2 = "GROUP BY id_lycee";
    }

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
        {$where}
        GROUP BY e.id
        ORDER BY e.nom";


    if ($result = $conn->query($sql)) {
        while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $statsServices[] = $row;
        }

        $result->free_result();
    }

    $sql =
        "SELECT
            {$select2}
            SUM(parent__differents_users) as parent__differents_users,
            SUM(eleve__differents_users) as eleve__differents_users,
            SUM(enseignant__differents_users) as enseignant__differents_users,
            SUM(perso_etab_non_ens__differents_users) as perso_etab_non_ens__differents_users,
            SUM(perso_collec__differents_users) as perso_collec__differents_users,
            SUM(tuteur_stage__differents_users) as tuteur_stage__differents_users
        FROM stats_etabs
        {$where}
        {$groupBy2}";


    if ($result = $conn->query($sql)) {
        if ($serviceView) {
            if ($row = $result->fetch_array(MYSQLI_ASSOC)) {
                $statsEtabs = $row;
            }
        } else {
            while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
                $statsEtabs[$row['id']] = $row;
            }
        }

        $result->free_result();
    }

    return ['statsServices' => $statsServices, 'statsEtabs' => $statsEtabs];
}

/**
 * Retourne la liste des types d'établissement
 *
 * @return array<string> Un tableau de types d'établissements
 */
function getTypesEtablissements()
{
    global $conn;
    $types = array();
    $sql = "SELECT distinct(type) as t FROM etablissements order by t asc";

    if ($res = $conn->query($sql)) {
        while ($row = $res->fetch_array()) {
            $types[] = $row['t'];
        }

        $res->free_result();
    }

    return $types;
}

/**
 * Retourne la liste des établissements en fonction du/des types d'établissement si ils sont présents
 *
 * @param array<string> $etabTypes Les types d'établissement à retourner
 *
 * @return array<string, string> Un tableau d'établissements
 */
function getEtablissements($etabTypes) {
    global $conn;
    $etabs = [];
    $where = count($etabTypes) === 0 ? "" : "WHERE ".generateWhereType($etabTypes);
    $sql = "SELECT * FROM etablissements {$where}";

    if ($res = $conn->query($sql)) {
        while ($row = $res->fetch_array()) {
            $etabs[$row['id']] = $row['nom'];
        }

        $res->free_result();
    }

    return $etabs;
}

/**
 * Écrit une ligne de ratio dans le tableau
 * 
 * @param int $nb    Le nombre d'utilisateur
 * @param int $total Le total d'utilisateurs
 *
 * @return string La ligne de ration pour le tableau
 */
function lineRatio($total, $nb) {
    return $total === 0 ? '0' : ''.round(($nb/$total)*100, 2)."%<br/>({$nb} / {$total})";
}

/**
 * Génère la clause where pour la sélection du mois
 *
 * @param string $month Le mois et l'année sous forme de chaîne de caractères
 *
 * @return string la clause where
 */
function generateWhereMonth($month) {
    if ($month === "-1") {
        return "";
    }

    $r = explode(' / ', $month);

    return "mois = {$r[0]} and annee = {$r[1]} ";
}

/**
 * Génère la clause where pour la sélection du type d'établissement
 *
 * @param string $etabTypes Les types d'établissement
 *
 * @return string la clause where
 */
function generateWhereType($etabTypes) {
    if (count($etabTypes) === 0) {
        return "";
    }

    return 'type IN ("'.implode('", "', $etabTypes).'")';
}

/**
 * Génère la liste des mois ordonnées et bien formaté
 *
 * @return array<string> La liste des mois
 */
function getListMois() {
    global $conn;
    $list = [];
    if ($res = $conn->query("SELECT DISTINCT(concat(LPAD(mois,2,'0'), ' / ', annee)) as m FROM stats_etabs ORDER BY annee DESC, m DESC")) {
        while ($row = $res->fetch_array()) {
            $list[] = $row['m'];
        }

        $res->free_result();
    }
    return $list;
}
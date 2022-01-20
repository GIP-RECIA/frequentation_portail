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

function nice($data)
{
    echo "<pre>" . print_r($data, true) . "</pre>";
}

function displayTable($etabId)
{
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

    $ajax = '
        <script type="text/javascript">
            $( document ).ready(function() {
                jQuery.fn.dataTableExt.oSort["percent-asc"]  = function(x,y) {
                    const xa = parseFloat(x.split("%")[0]);
                    const ya = parseFloat(y.split("%")[0]);
                    return ((xa < ya) ? -1 : ((xa > ya) ? 1 : 0));
                };
                 
                jQuery.fn.dataTableExt.oSort["percent-desc"] = function(x,y) {
                    const xa = parseFloat(x.split("%")[0]);
                    const ya = parseFloat(y.split("%")[0]);
                    return ((xa < ya) ? 1 : ((xa > ya) ? -1 : 0));
                };

                const perType = { "sType": "percent" };
                
                $(\'.top20\').click (function () {
                    $.ajax({
                        url: "./index.php?top",
                        type: "POST",
                        async: false, 
                        data: ({
                            serviceId: $(this).attr(\'data-serviceid\'),
                        }),
                        complete: function(data){
                            $(\'#topContent\').html(data.responseText);
                            $(\'#topModal\').modal(\'show\'); 
                        }
                    });
                });

                $(\'#result\').DataTable({ 
                    "paging": false,
                    "ordering": true,
                    dom: \'Bfrtip\',
                    buttons: [
                        {
                            extend: \'excelHtml5\',
                            exportOptions: {
                                format: {
                                    body: function (data, row, column, node) {
                                        if (column == 0) {
                                            return data.replace(/<\/?span[^>]*>/g,\'\').replace(\'TOP\',\'\');
                                        } else {
                                            return data.replace(/<br>/g,\' - \');
                                        }
                                    }
                                }
                            }    
                        }
                    ],
                    "aoColumns": [
                        null, null, null, null, null,
                        null, null, null, null, null, null,
                        perType, perType, perType, perType, perType, perType,
                    ]
                });

                $(\'input:radio[name="vue"]\').change(function(){
                    if ($(this).is(\':checked\')) {
                        $(\'#resultType\').val($(this).val());
                        $(\'#filterBtn\').click();
                    }
                });

                $(\'#reset\').click (function () {
                    $(\'#etab\').val(-1);
                    $(\'#etabType\').val(null);
                    $(\'#mois\').val(-1);
                    $(location).attr(\'href\',\'/\');
                });

                $(\'#etab\').select2();

                // Mutliple select Etablissement
                $(\'.js-select2-mutliple\').select2({
                    placeholder: "Tous le types"
                });

            })

        </script>    
    
    ';

    $html .= $ajax;

    return $html;
}

// TODO: revoir cette partie encore
function getTopHTML($serviceId)
{
    global $conn;
    $etabs = array();
    $html = "<table id='top20Desc' class='topResult'>";
    $html .= "<tr><td>Etablissement</td><td>Total</td><td>Elèves</td><td>Enseignants</td><td>Autres</td></tr>";

    $sql = "SELECT s.`id_lycee`, e.nom, SUM(s.total_visites) as total, nb_visiteurs, ceil(avg(eleve)) as eleve, ceil(avg(enseignant)) as enseignant, ceil(avg(parent)) as parent, ceil(avg(personnel_etablissement_non_enseignant)) as personnel_etablissement_non_enseignant, ceil(avg(personnel_collectivite)) as personnel_collectivite, se.total_eleve, se.total_enseignant, se.total_personnel_etablissement_non_enseignant, se.total_personnel_collectivite FROM `stats` as s , etablissements as e, stats_etab as se WHERE s.id_lycee = e.id and s.id_lycee = se.id_lycee and s.id_service = " . $conn->real_escape_string($serviceId) . "  group by id_lycee order by total desc limit 20";

    $stats = [];
    if ($res = $conn->query($sql)) {
        if ($row = $res->fetch_array()) {
            $stats = array_merge($stats, $row);
        }
        $res->free_result();
    }

    if ($res = $conn->query($sql)) {
        while ($row = $res->fetch_array()) {

            $eleves = round($row['eleve'] / $row['total_eleve'] * 100, 0);
            if (is_nan($eleves)) $eleves = 0;

            $enseignants = round($row['enseignant'] / $row['total_enseignant'] * 100, 0);
            if (is_nan($enseignants)) $enseignants = 0;

            $autres = round(($row['personnel_etablissement_non_enseignant'] + $row['personnel_collectivite']) / ($row['total_personnel_etablissement_non_enseignant'] + $row['total_personnel_collectivite']) * 100, 0);
            if (is_nan($autres)) $autres = 0;

            $html .= "<tr><td>" . $row['nom'] . "</td><td>" . $row['total'] . "</td><td>" . $eleves . "%</td><td>" . $enseignants . "%</td><td>" . $autres . "%</td></tr>";

        }
        $res->free_result();
    }
    $html .= "</table>";

    return $html;

}

function get_etablissement_id_by_siren($siren)
{
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
 * @param $etabId
 */
function getStatsHTML($etabId) {
    global $etab, $etabType, $resultType, $mois, $listMois, $show_simple_data;

    $serviceView = $resultType == 'services';
    $countMois = count($listMois);
    
    if ($mois !== '-1') {
        $countMois = 1;
    }

    $stats = getStats($etabId, $etabType);
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
        $avgParent = intval($parent/$countMois);
        $avgEleve = intval($eleve/$countMois);
        $avgEnseignant = intval($enseignant/$countMois);
        $avgPerso_etab_non_ens = intval($perso_etab_non_ens/$countMois);
        $avgPerso_collec = intval($perso_collec/$countMois);
        $avgTuteur_stage = intval($tuteur_stage/$countMois);
        $total_parent = intval($statsEtab['parent__total_pers_actives']);
        $total_eleve = intval($statsEtab['eleve__total_pers_actives']);
        $total_enseignant = intval($statsEtab['enseignant__total_pers_actives']);
        $total_perso_etab_non_ens = intval($statsEtab['perso_etab_non_ens__total_pers_actives']);
        $total_perso_collec = intval($statsEtab['perso_collec__total_pers_actives']);
        $total_tuteur_stage = intval($statsEtab['tuteur_stage__total_pers_actives']);
        $top = "";

        if ($serviceView) {
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

        $html .= "<td class=\"ratio-elem\">".lineRatio($total_parent, $avgParent)."</td>";
        $html .= "<td class=\"ratio-elem\">".lineRatio($total_eleve, $avgEleve)."</td>";
        $html .= "<td class=\"ratio-elem\">".lineRatio($total_enseignant, $avgEnseignant)."</td>";
        $html .= "<td class=\"ratio-elem\">".lineRatio($total_perso_etab_non_ens, $avgPerso_etab_non_ens)."</td>";
        $html .= "<td class=\"ratio-elem\">".lineRatio($total_perso_collec, $avgPerso_collec)."</td>";
        $html .= "<td class=\"ratio-elem\">".lineRatio($total_tuteur_stage, $avgTuteur_stage)."</td>";

        $html .= "</tr>";
    }

    return $html;
}

/**
 * Récupère les statistiques des différents services d'une établissement ou des différents établissement
 *
 * @param $etab     L'identifiant de l'établissement
 * @param $etabType
 */
function getStats($etab, $etabType) {
    global $conn, $resultType, $mois;

    $serviceView = $resultType == 'services';
    $where = [];
    $statsServices = [];
    $statsEtabs = [];
    $join = "";
    $from = "";

    if ($etab !== '-1') {
        $where[] = "id_lycee = {$etab}";
    }

    if ($mois !== '-1') {
        $r = explode(' / ', $mois);
        $m = $r[0];
        $a = $r[1];
        $where[] = "mois = " . $m . " and annee = " . $a . " ";
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
            id_lycee as id,
            SUM(parent__total_pers_actives) as parent__total_pers_actives,
            SUM(eleve__total_pers_actives) as eleve__total_pers_actives,
            SUM(enseignant__total_pers_actives) as enseignant__total_pers_actives,
            SUM(perso_etab_non_ens__total_pers_actives) as perso_etab_non_ens__total_pers_actives,
            SUM(perso_collec__total_pers_actives) as perso_collec__total_pers_actives,
            SUM(tuteur_stage__total_pers_actives) as tuteur_stage__total_pers_actives
        FROM stats_etabs
        {$where}
        GROUP BY id_lycee";

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

function getEtablissements()
{
    global $conn, $etabType;
    $etabs = array();
    $where = "";

    if ($etabType != '-1' && !empty($etabType)) {
        foreach ($etabType as $type) {
            $where_etab[] = "type = '" . $type . "'";
        }

        $where_etab = implode(' OR ', $where_etab);
        $where = "WHERE " . $where_etab . "";
    }

    $sql = "SELECT * FROM etablissements " . $where;

    if ($res = $conn->query($sql)) {
        while ($row = $res->fetch_array()) {
            $etabs[$row['id']] = $row['nom'];
        }

        $res->free_result();
    }
    return $etabs;
}

function getTypesEtablissements()
{
    global $conn;
    $types = array();
    $sql = "SELECT distinct(type) as t FROM etablissements order by t asc";

    if ($res = $conn->query($sql)) {
        while ($row = $res->fetch_array()) {
            $types[] = $row['t'];
        }

        $res->free_result($res);
    }
    return $types;
}

function lineRatio($total, $avg) {
    return $total === 0 ? '0' : ''.round(($avg/$total)*100, 2)."%<br/>({$avg} / {$total})";
}


function getListMois()
{
    global $conn;
    $list = array();
    if ($res = $conn->query("SELECT DISTINCT(concat(LPAD(mois,2,'0'), ' / ', annee)) as m FROM stats_etabs ORDER BY m ASC")) {
        while ($row = $res->fetch_array()) {
            $list[] = $row['m'];
        }

        $res->free_result();
    }
    return $list;
}
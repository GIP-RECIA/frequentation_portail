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

function displayTable($dateDebut, $dateFin, $etabId)
{
    global $resultType;

    $html = '<div class="table-responsive"><table id="result" class="table table-sm table-striped population">';

    $resultLabel = "";
    $results = array();

    if ($resultType == 'services') {
        $results = getServices();
        $resultLabel = "Service";
    } else {
        $results = getEtablissements();
        $resultLabel = "Etablissement";
    }

    $html .= '<thead class="thead-dark">';
    $html .= '<tr>';
    $html .= '<th></th>';
    $html .= '<th colspan="4">Visiteurs - Visites</th>';
    $html .= '<th colspan="5">Populations</th>';
    $html .= '<th colspan="4">Ratio par rapport aux utilisateurs potentiels</th>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<th>' . $resultLabel . '</th>';
    $html .= '<th>Au plus quatre fois</th>';
    $html .= '<th>Au moins cinq fois</th>';
    $html .= '<th>Nb. visiteurs</th>';
    $html .= '<th>Total visites</th>';
    $html .= '<th>Parent</th>';
    $html .= '<th>Elève</th>';
    $html .= '<th>Enseignant</th>';
    $html .= '<th>Personnel d\'établissement non enseignant</th>';
    $html .= '<th>Personnel de collectivité</th>';
    $html .= '<th>Elève</th>';
    $html .= '<th>Enseignant</th>';
    $html .= '<th>Personnel d\'établissement non enseignant</th>';
    $html .= '<th>Personnel de collectivité</th>';
    $html .= '</tr>';
    $html .= '</thead>';
    $html .= '<tbody>';
    // TODO: ici il faut insérer les lignes
    /*if ($resultType == 'services') {
        // TODO: en cours
        $results = getServices();
        $resultLabel = "Service";
    } else {
        // TODO: a faire
    }*/
    // TODO: dans un premier temps on ne va faire que les lignes des services en dur
    $html .= getStatsEtablissementHTML($etabId);
    $html .= '</tbody>';
    $html .= '</table>';
    $html .= '</div>';


    $resultsJson = json_encode(array_keys($results));

    // TODO: virer tous ce code qui va devenir mort
    $ajax = '
        <script type="text/javascript">

            var results = ' . $resultsJson . ';

            $( document ).ready(function() {

                //results.forEach(displayResult);

                function displayResult(value, index, array) {
                    $.ajax({
                        url: "./index.php",
                        type: "POST",
                        async: true, 
                        data: ({
                            resultId: value,
                            dateDebut: $(\'#dateDebut\').val(),
                            dateFin: $(\'#dateFin\').val(),
                            etab: $(\'#etab\').val(),
                            etabType: $(\'#etabType\').val(),
                            mois: $(\'#mois\').val(),
                            resultType: $(\'#resultType\').val()
                        }),
                        complete: function(data){
                            $(\'#result > tbody:last-child\').append(data.responseText);

                            if (index == (array.length - 1)) {

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
                                                        }
                                                        else
                                                            return data;
                                                    }
                                                }
                                            }    
                                        }
                                    ]
                                });

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

                            }
                        }
                    });
                }   
                
                /*$(document).ready( function () {
                    $(\'#result\').DataTable({ 
                        "paging": false,
                        "ordering": true,
                        dom: \'Bfrtip\',
                        buttons: [
                            \'excel\'
                        ]
                    });
                } );*/

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

function getTopHTML($serviceId)
{
    global $conn;
    $etabs = array();
    $html = "<table id='top20Desc' class='topResult'>";
    $html .= "<tr><td>Etablissement</td><td>Total</td><td>Elèves</td><td>Enseignants</td><td>Autres</td></tr>";

    $sql = "SELECT s.`id_lycee`, e.nom, SUM(s.total_visites) as total, nb_visiteurs, ceil(avg(eleve)) as eleve, ceil(avg(enseignant)) as enseignant, ceil(avg(parent)) as parent, ceil(avg(personnel_etablissement_non_enseignant)) as personnel_etablissement_non_enseignant, ceil(avg(personnel_collectivite)) as personnel_collectivite, se.total_eleve, se.total_enseignant, se.total_personnel_etablissement_non_enseignant, se.total_personnel_collectivite FROM `stats` as s , etablissements as e, stats_etab as se WHERE s.id_lycee = e.id and s.id_lycee = se.id_lycee and s.id_service = " . $conn->real_escape_string($serviceId) . "  group by id_lycee order by total desc limit 20";

    $stats = [];
    if ($res = mysqli_query($conn, $sql)) {
        if ($row = mysqli_fetch_array($res)) {
            $stats = array_merge($stats, $row);
        }
        mysqli_free_result($res);
    }

    if ($res = mysqli_query($conn, $sql)) {
        while ($row = mysqli_fetch_array($res)) {

            $eleves = round($row['eleve'] / $row['total_eleve'] * 100, 0);
            if (is_nan($eleves)) $eleves = 0;

            $enseignants = round($row['enseignant'] / $row['total_enseignant'] * 100, 0);
            if (is_nan($enseignants)) $enseignants = 0;

            $autres = round(($row['personnel_etablissement_non_enseignant'] + $row['personnel_collectivite']) / ($row['total_personnel_etablissement_non_enseignant'] + $row['total_personnel_collectivite']) * 100, 0);
            if (is_nan($autres)) $autres = 0;

            $html .= "<tr><td>" . $row['nom'] . "</td><td>" . $row['total'] . "</td><td>" . $eleves . "%</td><td>" . $enseignants . "%</td><td>" . $autres . "%</td></tr>";

        }
        mysqli_free_result($res);
    }
    $html .= "</table>";

    return $html;

}

function get_etablissement_id_by_siren($siren)
{
    global $conn;
    $sql = 'SELECT * from etablissements where siren = "' . $siren . '"';
    $query = $conn->query($sql);
    $result = $query->fetch_assoc();
    if (isset($result['id'])) return $result['id'];
    return false;
}

function getStatsEtablissementHTML($etabId) {
    global $dateDebut, $dateFin, $etab, $etabType, $resultType, $mois, $listMois, $show_simple_data;

    //print_r(getStatsEtablissement($etabId, $etabType));
    $stats = getStatsEtablissement($etabId, $etabType);
    $statsServices = $stats['statsServices'];
    $statsEtab = $stats['statsEtab'];
    $html = '';

    foreach ($statsServices as $service) {
        $html .= "<tr>";

        $html .= "<td><span class=\"top20\" data-serviceid=\"{$service['id']}\" class=\"float-right\">TOP</span>{$service['nom']}</td>";
        $html .= "<td>{$service['au_plus_quatre_fois']}</td>";
        $html .= "<td>{$service['au_moins_cinq_fois']}</td>";
        $html .= "<td>{$service['differents_users']}</td>";
        $html .= "<td>{$service['total_sessions']}</td>";

        $html .= "<td></td>";
        $html .= "<td></td>";
        $html .= "<td></td>";
        $html .= "<td></td>";
        $html .= "<td></td>";

        $html .= "<td></td>";
        $html .= "<td></td>";
        $html .= "<td></td>";
        $html .= "<td></td>";

        $html .= "</tr>";
    }

    return $html;
}

function getStatsHTML($resultId)
{

    global $dateDebut, $dateFin, $etab, $etabType, $resultType, $mois, $listMois, $show_simple_data;

    if ($resultType == 'services')
        $resultNom = getServiceNameFromId($resultId);

    else
        $resultNom = getEtabNameFromId($resultId);


    $html = '<tr>';

    $top = "";
    if ($resultType == 'services' && !$show_simple_data)
        $top = '<span class="top20" data-serviceid="' . $resultId . '" class="float-right">TOP</span>';

    $html .= '<td>' . $top . $resultNom . '</td>';

    $stats = getStats($dateDebut, $dateFin, $resultId, $etab, $etabType);

    $html .= '<td>' . $stats['au_plus_quatre_fois'] . '</td>';
    $html .= '<td>' . $stats['au_moins_cinq_fois'] . '</td>';
    $html .= '<td>' . $stats['nb_visiteurs'] . '</td>';
    $html .= '<td>' . $stats['total_visites'] . '</td>';

    $parent = intval($stats['parent']); //ceil(intval($stats['parent']) / $days);
    $eleve = intval($stats['eleve']); //ceil(intval($stats['eleve']) / $days);
    $enseignant = intval($stats['enseignant']); //ceil(intval($stats['enseignant']) / $days);
    $personnelEtablissementNonEnseignant = intval($stats['personnel_etablissement_non_enseignant']); //ceil(intval($stats['personnel_etablissement_non_enseignant']) / $days);
    $personnelCollectivite = intval($stats['personnel_collectivite']);//ceil(intval($stats['personnel_collectivite']) / $days);

    $countMois = count($listMois);
    if ($mois != '-1')
        $countMois = 1;

    $avgParent = ceil(intval($stats['parent']) / $countMois);
    $avgEleve = ceil(intval($stats['eleve']) / $countMois);
    $avgEnseignant = ceil(intval($stats['enseignant']) / $countMois);
    $avgPrsonnelEtablissementNonEnseignant = ceil(intval($stats['personnel_etablissement_non_enseignant']) / $countMois);
    $avgPersonnelCollectivite = ceil(intval($stats['personnel_collectivite']) / $countMois);


    $html .= '<td>' . $parent . '</td>';
    $html .= '<td>' . $eleve . '</td>';
    $html .= '<td>' . $enseignant . '</td>';
    $html .= '<td>' . $personnelEtablissementNonEnseignant . '</td>';
    $html .= '<td>' . $personnelCollectivite . '</td>';

    // Ici cela provient de la table stats_etab, sauf que cette table n'est jamais remplie (total_eleve, total_enseignant, total_personnel_etablissement_non_enseignant, total_personnel_collectivite)
    // res : 0% (car ici on a rien en base) <br> VAL (car provient d'une autre table) / VIDE (car on a rien en base)
    // ATTENTION, ici on fait un intvalue sur le résultat booléen de la comparaison au lien du champ numérique ????
    $html .= '<td>' . ((intval($stats['total_eleve'] == 0)) ? '0' : (round($avgEleve / intval($stats['total_eleve']) * 100, 0))) . '%<br/> (' . $avgEleve . ' / ' . $stats['total_eleve'] . ')</td>';
    $html .= '<td>' . ((intval($stats['total_enseignant'] == 0)) ? '0' : (round($avgEnseignant / intval($stats['total_enseignant']) * 100, 0))) . '%<br/> (' . $avgEnseignant . ' / ' . $stats['total_enseignant'] . ')</td>';
    $html .= '<td>' . ((intval($stats['total_personnel_etablissement_non_enseignant'] == 0)) ? '0' : (round($avgPrsonnelEtablissementNonEnseignant / intval($stats['total_personnel_etablissement_non_enseignant']) * 100, 0))) . '%</td>';
    $html .= '<td>' . ((intval($stats['total_personnel_collectivite'] == 0)) ? '0' : (round($avgPersonnelCollectivite / intval($stats['total_personnel_collectivite']) * 100, 0))) . '%</td>';

    $html .= '</tr>';

    return $html;

}

/**
 * Récupère les statistiques des différents services d'une établissement
 */
function getStatsEtablissement($etab, $etabType) {
    global $conn, $resultType, $mois;

    $where = [];
    $statsServices = [];

    if ($etab !== '-1') {
        $where[] = "id_lycee = {$etab}";
    }

    $where = implode(' AND ', $where);

    if ($where !== '') {
        $where = " WHERE {$where}";
    }

    $sql =
        "SELECT
            sv.id as id,
            sv.nom as nom,
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
        FROM stats_services as s
        INNER JOIN services as sv ON sv.id = s.id_service
        {$where}
        GROUP BY id_service
        ORDER BY nom";

    if ($result = $conn->query($sql)) {
        while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $statsServices[] = $row;
        }
    }

    return ['statsServices' => $statsServices, 'statsEtab' => []];
}

function getStats($dateDebut, $dateFin, $idResult, $etab, $etabType)
{
    global $conn, $resultType, $mois;

    $stats = array(
        'au_plus_quatre_fois' => 0,
        'au_moins_cinq_fois' => 0,
        'nb_visiteurs' => 0,
        'total_visites' => 0,
        'parent' => 0,
        'eleve' => 0,
        'enseignant' => 0,
        'personnel_etablissement_non_enseignant' => 0,
        'personnel_collectivite' => 0,
        'total_eleve' => 0,
        'total_enseignant' => 0,
        'total_personnel_etablissement_non_enseignant' => 0,
        'total_personnel_collectivite' => 0,
    );

    $where = array();
    $table = "stats";

    $groupby = "id_service";

    if ($resultType == 'services') {
        $where[] = "id_service = " . $idResult . " ";
    } else {
        $etab = $idResult;
        $table = "stats_etab_mois";
        $groupby = "id_lycee";
    }

    if ($mois != '-1') {
        $r = explode(' / ', $mois);
        $m = $r[0];
        $a = $r[1];
        $where[] = "mois = " . $m . " and annee = " . $a . " ";
    }

    /*if ($etab != '-1' && ($resultType != 'services')  ) {
        $where[] = " id_lycee = " . $etab;
    }*/

    if ($etab != '-1') {
        $where[] = " id_lycee = " . $etab;
    }

    if ((count($etabType) > 0) && ($resultType == 'services')) {
        $etabs = "";
        $sep = "";
        foreach ($etabType as $t) {
            $etabs .= $sep . "'" . $t . "'";
            $sep = ",";
        }
        $where[] = "  id_lycee IN (SELECT id FROM `etablissements` WHERE `type` IN (" . $etabs . "))";
    }

    $where = implode(' AND ', $where);
    $where = ' WHERE ' . $where;

    if ($where == ' WHERE ')
        $where = '';

    $sql = "SELECT
        SUM(au_plus_quatre_fois) as au_plus_quatre_fois,
        SUM(au_moins_cinq_fois) as au_moins_cinq_fois,
        SUM(nb_visiteurs) as nb_visiteurs,
        SUM(total_visites) as total_visites,
        CEIL(SUM(parent)) as parent,
        CEIL(SUM(eleve)) as eleve,
        CEIL(SUM(enseignant)) as enseignant,
        CEIL(SUM(personnel_etablissement_non_enseignant)) as personnel_etablissement_non_enseignant,
        CEIL(SUM(personnel_collectivite)) as personnel_collectivite
        FROM " . $table . "
        " . $where . "
        GROUP BY " . $groupby;

    if ($res = mysqli_query($conn, $sql)) {
        if ($row = mysqli_fetch_array($res)) {
            $stats = $row;
        }
        mysqli_free_result($res);
    }

    $table = "stats_etab";
    $where = array();

    if ($etab != '-1' && empty($etabType)) {
        $where[] = "  id_lycee = " . $etab;
    }

    if (count($etabType) > 0) {
        $etabs = "";
        $sep = "";
        foreach ($etabType as $t) {
            $etabs .= $sep . "'" . $t . "'";
            $sep = ",";
        }
        $where[] = "  id_lycee IN (SELECT id FROM `etablissements` WHERE `type` IN (" . $etabs . "))";
    }

    $where = implode(' AND ', $where);
    $where = ' WHERE ' . $where;

    if ($where == ' WHERE ')
        $where = '';

    $sql = "SELECT 
                CEIL(SUM(total_eleve)) as total_eleve, 
                CEIL(SUM(total_enseignant)) as total_enseignant, 
                CEIL(SUM(total_personnel_etablissement_non_enseignant)) as total_personnel_etablissement_non_enseignant,
                CEIL(SUM(total_personnel_collectivite)) as total_personnel_collectivite
                FROM " . $table . "                
                " . $where . "        
                ";
    if ($res = mysqli_query($conn, $sql)) {
        if ($row = mysqli_fetch_array($res)) {
            $stats = array_merge($stats, $row);
        }
        mysqli_free_result($res);
    }

    return $stats;
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

    if ($res = mysqli_query($conn, $sql)) {
        while ($row = mysqli_fetch_array($res)) {
            $etabs[$row['id']] = $row['nom'];
        }
        mysqli_free_result($res);
    }
    return $etabs;
}

function getTypesEtablissements()
{
    global $conn;
    $types = array();
    $sql = "SELECT distinct(type) as t FROM etablissements order by t asc";
    if ($res = mysqli_query($conn, $sql)) {
        while ($row = mysqli_fetch_array($res)) {
            $types[] = $row['t'];
        }
        mysqli_free_result($res);
    }
    return $types;
}

function getServices()
{
    global $conn;
    $services = array();
    $sql = "SELECT * FROM services";
    if ($res = mysqli_query($conn, $sql)) {
        while ($row = mysqli_fetch_array($res)) {
            $services[$row['id']] = $row['nom'];
        }
        mysqli_free_result($res);
    }
    return $services;
}

function getServiceNameFromId($serviceId)
{
    global $conn;
    $name = "";
    $sql = "SELECT nom FROM services WHERE id = " . $serviceId;
    if ($res = mysqli_query($conn, $sql)) {
        $row = mysqli_fetch_assoc($res);
        $name = $row['nom'];
        mysqli_free_result($res);
    }
    return $name;
}

function getEtabNameFromId($etabId)
{
    global $conn;
    $name = "";
    $sql = "SELECT nom FROM etablissements WHERE id = " . $etabId;
    if ($res = mysqli_query($conn, $sql)) {
        $row = mysqli_fetch_assoc($res);
        $name = $row['nom'];
        mysqli_free_result($res);
    }
    return $name;
}

/**
 * Fonction inutilisé ?
 */
function updateEtablissement($folder)
{
    global $conn;
    $xml = simplexml_load_file($folder . '/etablissements_etat_lieux.xml');
    $etabs = $xml->xpath('/Etablissements/Etablissement');
    foreach ($etabs as $etab) {
        if (strlen($etab['siren']) > 0)
            $sql = "UPDATE etablissements set type = '" . $etab['type'] . "' WHERE siren = '" . $etab['siren'] . "'";
        $conn->query($sql);
    }
}


function getListMois()
{
    global $conn;
    $list = array();
    if ($res = mysqli_query($conn, "SELECT DISTINCT(concat(LPAD(mois,2,'0'), ' / ', annee)) as m FROM stats_etabs ORDER BY m ASC")) {
        while ($row = mysqli_fetch_array($res)) {
            $list[] = $row['m'];
        }
        mysqli_free_result($res);
    }
    return $list;
}


function getDateDebutMin()
{
    global $conn;
    $dateDebut = '1970-01-01';
    $result = mysqli_query($conn, "SELECT concat(annee, '-', LPAD(mois,2,'0')) as d FROM stats ORDER BY d ASC LIMIT 1");
    $row = mysqli_fetch_assoc($result);
    if ($row) {
        $dateDebut = $row['jour'];
    }

    mysqli_free_result($result);
    return $dateDebut;
}

function getDateFinMax()
{
    global $conn;
    $dateFin = '1970-01-01';
    $result = mysqli_query($conn, "SELECT max(jour) as jour FROM stats");
    $row = mysqli_fetch_assoc($result);
    if ($row) {
        $dateFin = $row['jour'];
    }
    mysqli_free_result($result);
    return $dateFin;
}


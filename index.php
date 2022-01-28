<?php

require './vendor/autoload.php';
require './include/cas.php';
require './include/db.php';
require './include/web-functions.php';

const VIEW_SERVICES = "services";
const VIEW_ETABS = "etabs";

function main() {
    $configs = include('./include/config.php');
    
    casInit($configs['cas']);
    
    if (isset($_REQUEST['logout'])) {
        casLogout();
    }
    
    $pdo = getNewPdo($configs['db']);
    
    //$_SESSION['phpCAS']['attributes']['ESCOSIRENCourant'] = "19450042700035"; //durzy
    //unset($_SESSION['phpCAS']['attributes']['ESCOSIRENCourant']);
    $siren = getCasAttribute('ESCOSIRENCourant');
    //enseignant: National_ENS
    //directeur: National_DIR
    $role = getCasAttribute('ENTPersonProfils');
    $etablissement = !empty($siren) ? get_etablissement_id_by_siren($pdo, $siren) : null;
    $etabReadOnly = $etablissement !== null ? true : false;
    $show_simple_data = !empty($etablissement) && $role == "National_DIR";
    
    if (!empty($etablissement)) {
        $_REQUEST["etab"] = $etablissement;
    }
    
    $mois = "-1";
    $etab = "-1";
    $resultType = VIEW_SERVICES;
    $etabType = [];
    
    $listMois = getListMois($pdo);
    
    if (isset($_REQUEST["etabType"]))
        $etabType = $_REQUEST["etabType"];
    
    //$resultType = "etabs";
    if (isset($_REQUEST["etab"]))
        $etab = $_REQUEST["etab"];
    
    if (isset($_REQUEST["mois"]))
        $mois = $_REQUEST["mois"];
    
    if (isset($_REQUEST["resultType"]))
        $resultType = $_REQUEST["resultType"];
    
    /*if (isset($_REQUEST["resultId"])) {
        echo getStatsHTML($_REQUEST["resultId"]);
        die;
    }*/
    
    if (isset($_REQUEST["top"])) {
        echo getTopHTML($pdo, $_REQUEST["serviceId"], $mois);
        die;
    }

    // le dossier ou on trouve les templates
    $loader = new Twig\Loader\FilesystemLoader('templates');

    // initialiser l'environement Twig
    $twig = new Twig\Environment($loader);

    // load template
    $template = $twig->load('index.html.twig');

    // set template variables
    // render template
    echo $template->render([
        'showSimpleData' => $show_simple_data,
        'etabReadOnly' => $etabReadOnly,
        'viewService' => $resultType === VIEW_SERVICES,
        'resultType' => $resultType,
        'listMois' => $listMois,
        'listEtabs' => getEtablissements($pdo, $etabType),
        'listTypesEtab' => getTypesEtablissements($pdo),
        'mois' => $mois,
        'etab' => $etab,
        'typesEtab' => $etabType,
        'table' => displayTable($pdo, $etab, $resultType, $etabType, $mois, $show_simple_data),
    ]);
    
    $pdo = null;
}

main();

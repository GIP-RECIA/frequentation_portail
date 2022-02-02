<?php

require './vendor/autoload.php';
require './include/cas.php';
require './include/db.php';
require './include/web-functions.php';

const VIEW_SERVICES = "services";
const VIEW_ETABS = "etabs";

function main(): void {
    try {
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
        $mois = null;
        $listMois = getListMois($pdo, $siren);
        
        if (isset($_REQUEST["mois"])) {
            $mois = intval($_REQUEST["mois"]);
        // Si aucun mois n'est sélectionné, on prends le premier de la liste qui est le plus récent
        } else if (count($listMois) > 0) {
            $mois = intval($listMois[0]['id']);
        }

        $etablissement = !empty($siren) ? get_etablissement_id_by_siren($pdo, $mois, $siren) : null;
        $etabReadOnly = $etablissement !== null;
        $show_simple_data = $etablissement !== null && $role == "National_DIR";
        
        if ($etablissement !== null) {
            $_REQUEST["etab"] = $etablissement;
        }
        
        $etab = -1;
        $resultType = VIEW_SERVICES;
        $etabType = [];
        $serviceView = true;
        
        
        if (isset($_REQUEST["etabType"])) {
            $etabType = array_map('intval', $_REQUEST["etabType"]);
        }
        
        if (isset($_REQUEST["etab"])) {
            $etab = intval($_REQUEST["etab"]);
        }
        
        if (isset($_REQUEST["resultType"])) {
            $serviceView = $_REQUEST["resultType"] !== VIEW_ETABS;
        }
        
        if (isset($_REQUEST["top"])) {
            $templateFile = 'top.html.twig';
            $templateDate = ['table' => getTopData($pdo, $_REQUEST["serviceId"], $mois)];
        } else {
            $templateFile = 'index.html.twig';
            $templateDate = [
                'showSimpleData' => $show_simple_data,
                'etabReadOnly' => $etabReadOnly,
                'viewService' => $serviceView,
                'listMois' => $listMois,
                'listTypesEtab' => getTypesEtablissements($pdo, $mois),
                'listEtabs' => getEtablissements($pdo, $mois, $etabType),
                'mois' => $mois,
                'typesEtab' => $etabType,
                'etab' => $etab,
                'table' => getDataTable($pdo, $etab, $serviceView, $etabType, $mois, $show_simple_data),
            ];
        }

        //print_r($templateDate['listEtabs']);

        $pdo = null;

        // le dossier ou on trouve les templates
        $loader = new Twig\Loader\FilesystemLoader('templates');
        // initialiser l'environnement Twig
        $twig = new Twig\Environment($loader);
        // load template
        $template = $twig->load($templateFile);
        // set template variables
        // render template
        echo $template->render($templateDate);
    } catch (Exception $e) {
        http_response_code(500);
        echo "Server Error";

        // Si on est pas en prod, on affiche l'erreur, sinon on la log
        if (array_key_exists('env', $configs) && $configs['env'] !== "prod") {
            $trace = $e->getTrace();
            echo "<br>".$e->getMessage();
            echo "<br>Fichier : ".$trace[0]['file'].":".$trace[0]['line'];
        } else {
            error_log($e->getMessage());
        }
    }
}

main();

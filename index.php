<?php

require 'vendor/autoload.php';
require 'include/web-functions.php';

use App\Cas;

const VIEW_SERVICES = "services";
const VIEW_ETABS = "etabs";

function main(): void {
    try {
        $cas = Cas::getInstance();
        $cas->init();
        
        if (isset($_REQUEST['logout'])) {
            $cas->logout();
        }
        
        //$_SESSION['phpCAS']['attributes']['ESCOSIRENCourant'] = "19450042700035"; //durzy
        //unset($_SESSION['phpCAS']['attributes']['ESCOSIRENCourant']);
        $siren = $cas->getAttribute('ESCOSIRENCourant');
        //enseignant: National_ENS
        //directeur: National_DIR
        $role = $cas->getAttribute('ENTPersonProfils');
        $mois = null;
        $listMois = getListMois($siren);
        
        if (isset($_REQUEST["mois"])) {
            $mois = intval($_REQUEST["mois"]);
        // Si aucun mois n'est sélectionné, on prends le premier de la liste qui est le plus récent
        } else if (count($listMois) > 0) {
            $mois = intval($listMois[0]['id']);
        }

        $etablissement = !empty($siren) ? get_etablissement_id_by_siren($mois, $siren) : null;
        $etabReadOnly = $etablissement !== null;
        $show_simple_data = $etablissement !== null && $role == "National_DIR";
        
        if ($etablissement !== null) {
            $_REQUEST["etab"] = $etablissement;
        }
        
        $etab = -1;
        $resultType = VIEW_SERVICES;
        $departement = [];
        $etabType = [];
        $etabType2 = [];
        $serviceView = true;
        
        
        if (isset($_REQUEST["etabType"])) {
            $etabType = array_map('intval', $_REQUEST["etabType"]);
        }
        
        if (isset($_REQUEST["etabType2"])) {
            $etabType2 = array_map('intval', $_REQUEST["etabType2"]);
        }
        
        if (isset($_REQUEST["departement"])) {
            $departement = array_map('intval', $_REQUEST["departement"]);
        }
        
        if (isset($_REQUEST["etab"])) {
            $etab = intval($_REQUEST["etab"]);
        }
        
        if (isset($_REQUEST["resultType"])) {
            $serviceView = $_REQUEST["resultType"] !== VIEW_ETABS;
        }
        
        $templateDate = [
            'showSimpleData' => $show_simple_data,
            'etabReadOnly' => $etabReadOnly,
            'viewService' => $serviceView,
            'listMois' => $listMois,
            'listDepartements' => getDepartements($mois),
            'listTypesEtab' => getTypesEtablissements($mois),
            'listTypes2Etab' => getTypes2Etablissements($mois, $etabType),
            'listEtabs' => getEtablissements($mois, $etabType, $etabType2, $departement),
            'mois' => $mois,
            'departement' => $departement,
            'typesEtab' => $etabType,
            'types2Etab' => $etabType2,
            'etab' => $etab,
            // TODO: ajouter les départements et les etabTypes2 dans cette fonction pour filtrer
            'table' => getDataTable($etab, $serviceView, $mois, $departement, $etabType, $etabType2, $show_simple_data),
        ];

        // le dossier ou on trouve les templates
        $loader = new Twig\Loader\FilesystemLoader('templates');
        // initialiser l'environnement Twig
        $twig = new Twig\Environment($loader);
        // load template
        $template = $twig->load('index.html.twig');
        // set template variables
        // render template
        echo $template->render($templateDate);
    } catch (Exception $e) {
        showException($e);
    }
}

main();

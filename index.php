<?php

require 'vendor/autoload.php';
require 'include/web-functions.php';

use App\Cas;
use App\DroitsUtilisateur;
use App\NoEtabToDisplayException;

const VIEW_SERVICES = "services";
const VIEW_ETABS = "etabs";

function main(): void {
    try {
        $cas = Cas::getInstance();
        $cas->init();
        
        if (isset($_POST['logout'])) {
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
        
        if (isset($_POST["mois"])) {
            $mois = intval($_POST["mois"]);
        // Si aucun mois n'est sélectionné, on prends le premier de la liste qui est le plus récent
        } else if (count($listMois) > 0) {
            $mois = intval($listMois[0]['id']);
        }

        $etablissement = !empty($siren) ? get_etablissement_id_by_siren($mois, $siren) : null;
        $etabReadOnly = $etablissement !== null;
        $show_simple_data = $etablissement !== null && $role == "National_DIR";
        
        if ($etablissement !== null) {
            $_POST["etab"] = $etablissement;
        }
        
        $etab = -1;
        $resultType = VIEW_SERVICES;
        $departement = [];
        $etabType = [];
        $etabType2 = [];
        $serviceView = true;
        $droitsUtilisateur = new DroitsUtilisateur();
        
        if (isset($_POST["etabType"])) {
            $etabType = array_map('intval', $_POST["etabType"]);
        }
        
        if (isset($_POST["etabType2"])) {
            $etabType2 = array_map('intval', $_POST["etabType2"]);
        }
        
        if (isset($_POST["departement"])) {
            $departement = array_map('intval', $_POST["departement"]);
        }
        
        if (isset($_POST["etab"])) {
            $etab = intval($_POST["etab"]);
        }
        
        if (isset($_POST["resultType"])) {
            $serviceView = $_POST["resultType"] !== VIEW_ETABS;
        }
        
        $templateDate = [
            'domain' => $_SERVER['HTTP_HOST'],
            'showSimpleData' => $show_simple_data,
            'etabReadOnly' => $etabReadOnly,
            'viewService' => $serviceView,
            'listMois' => $listMois,
            'listDepartements' => getDepartements($droitsUtilisateur, $mois),
            'listTypesEtab' => getTypesEtablissements($droitsUtilisateur, $mois, $departement),
            'listTypes2Etab' => getTypes2Etablissements($droitsUtilisateur, $mois, $departement, $etabType),
            'listEtabs' => getEtablissements($droitsUtilisateur, $mois, $departement, $etabType, $etabType2),
            'mois' => $mois,
            'departement' => $departement,
            'typesEtab' => $etabType,
            'types2Etab' => $etabType2,
            'etab' => $etab,
            'table' => getDataTable($droitsUtilisateur, $etab, $serviceView, $mois, $departement, $etabType, $etabType2, $show_simple_data),
        ];

        echo renderTwig('index.html.twig', $templateDate);
    } catch (NoEtabToDisplayException $e) {
        echo renderTwig('exception.html.twig', ['message' => "Vous n'avez le droit de voir aucun établissement"]);
    } catch (Exception $e) {
        showException($e);
    }
}

main();

<?php

require 'vendor/autoload.php';
require 'include/web-functions.php';

use App\Cas;
use App\DroitsUtilisateur;

const VIEW_SERVICES = "services";
const VIEW_ETABS = "etabs";

function main(): void {
    try {
        $cas = Cas::getInstance();
        $cas->init();

        $mois = $_POST["mois"];
        $pos = $_POST["pos"];
        $departement = [];
        $etabType = [];
        $etabType2 = [];

        if (isset($_POST["departement"])) {
            $departement = array_map('intval', $_POST["departement"]);
        }

        if (isset($_POST["etabType"])) {
            $etabType = array_map('intval', $_POST["etabType"]);
        }

        if (isset($_POST["etabType2"])) {
            $etabType2 = array_map('intval', $_POST["etabType2"]);
        }

        if(!is_numeric($mois)) {
            throw new Exception('donnée de mois invalide');
        }

        if(!is_numeric($pos)) {
            throw new Exception('donnée de position invalide');
        }
        
        $mois = intval($mois);
        $pos = intval($pos);
        $res = [];
        $droitsUtilisateur = new DroitsUtilisateur();

        switch ($pos) {
            case 1:
                $res['departements'] = getDepartements($droitsUtilisateur, $mois);
            case 2:
                $res['types'] = getTypesEtablissements($droitsUtilisateur, $mois, $departement);
            case 3:
                $res['types2'] = getTypes2Etablissements($droitsUtilisateur, $mois, $departement, $etabType);
            case 4:
                $res['etabs'] = getEtablissements($droitsUtilisateur, $mois, $departement, $etabType, $etabType2);
        }

        header('Content-type: application/json');
        echo json_encode($res);
    } catch (Exception $e) {
        showException($e);
    }
}

main();
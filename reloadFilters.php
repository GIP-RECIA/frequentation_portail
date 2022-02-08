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
        $pdo = getNewPdo($configs['db']);

        $mois = $_REQUEST["mois"];
        $pos = $_REQUEST["pos"];
        $departement = [];
        $etabType = [];
        $etabType2 = [];

        if (isset($_REQUEST["departement"])) {
            $departement = array_map('intval', $_REQUEST["departement"]);
        }

        if (isset($_REQUEST["etabType"])) {
            $etabType = array_map('intval', $_REQUEST["etabType"]);
        }

        if (isset($_REQUEST["etabType2"])) {
            $etabType2 = array_map('intval', $_REQUEST["etabType2"]);
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

        switch ($pos) {
            case 1:
                $res['departements'] = getDepartements($pdo, $mois);
                $res['types'] = getTypesEtablissements($pdo, $mois);
            case 3:
                $res['types2'] = getTypes2Etablissements($pdo, $mois, $etabType);
            case 2:
            case 4:
                $res['etabs'] = getEtablissements($pdo, $mois, $etabType, $etabType2, $departement);
        }

        $pdo = null;

        header('Content-type: application/json');
        echo json_encode($res);
    } catch (Exception $e) {
        showException($e, $configs['env']);
    }
}

main();
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
        $etabType = [];
        $etabType2 = [];

        if (isset($_REQUEST["etabType"])) {
            $etabType = array_map('intval', $_REQUEST["etabType"]);
        }

        if (isset($_REQUEST["etabType2"])) {
            $etabType2 = array_map('intval', $_REQUEST["etabType2"]);
        }

        if(!is_numeric($mois)) {
            throw new Exception('donnée de mois invalide');
        }
        
        $mois = intval($mois);

        $res = [
            'types' => getTypesEtablissements($pdo, $mois),
            'types2' => getTypes2Etablissements($pdo, $mois, $etabType),
            'etabs' => getEtablissements($pdo, $mois, $etabType, $etabType2),
        ];

        $pdo = null;

        header('Content-type: application/json');
        echo json_encode($res);
    } catch (Exception $e) {
        showException($e, $configs['env']);
    }
}

main();
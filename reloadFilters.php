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

        if (isset($_REQUEST["etabType"])) {
            $etabType = array_map('intval', $_REQUEST["etabType"]);
        } else {
            $etabType = [];
        }

        if(!is_numeric($mois)) {
            throw new Exception('donnée de mois invalide');
        }
        
        $mois = intval($mois);

        $res = [
            'types' => getTypesEtablissements($pdo, $mois),
            'etabs' => getEtablissements($pdo, $mois, $etabType),
        ];

        header('Content-type: application/json');
        echo json_encode($res);
    } catch (Exception $e) {
        echo "hello";
        showException($e, $configs['env']);
    }
}

main();
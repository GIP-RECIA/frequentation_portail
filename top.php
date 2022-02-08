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
        $serviceId = $_REQUEST["serviceId"];
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

        if(!is_numeric($serviceId)) {
            throw new Exception('donnée de service invalide');
        }
        
        $mois = intval($mois);
        $serviceId = intval($serviceId);
        $templateDate = ['table' => getTopData($pdo, $serviceId, $mois, $departement, $etabType, $etabType2)];
        $pdo = null;
        // le dossier ou on trouve les templates
        $loader = new Twig\Loader\FilesystemLoader('templates');
        // initialiser l'environnement Twig
        $twig = new Twig\Environment($loader);
        // load template
        $template = $twig->load('top.html.twig');
        // set template variables
        // render template
        echo $template->render($templateDate);
    } catch (Exception $e) {
        showException($e, $configs['env']);
    }
}

main();

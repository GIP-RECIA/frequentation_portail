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

        $mois = $_POST["mois"];
        $serviceId = $_POST["serviceId"];
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

        if(!is_numeric($serviceId)) {
            throw new Exception('donnée de service invalide');
        }
        
        $mois = intval($mois);
        $serviceId = intval($serviceId);
        $templateDate = ['table' => getTopData($serviceId, $mois, $departement, $etabType, $etabType2)];
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
        showException($e);
    }
}

main();

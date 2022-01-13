<?php

require './include/import-functions.php';

$configs = include('./include/config.php');

if (!isset($argv)) {
    die;
}

$db = $configs['db'];
$conn = new mysqli($db['host'], $db['user'], $db['password'], $db['dbName'], $db['port']);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error . "\n");
}

$options = getopt('d:c:');

$date = isset($options['d']) ? $options['d'] : null;
$chemin = isset($options['c']) ? $options['c'] : $configs['importDir'];
$folder = date('Y/m');
$path = '';

if ($date != null) {
    $folder = $date;
}

if ($chemin != null) {
    if (!is_dir($chemin)) {
        die("Le chemin précisé n'existe pas\n");
    }

    $path = is_dir($chemin) ? $chemin : $path;

    if (substr($path, -1) != '/') {
        $path = $path . '/';
    }
}

if ($path == '/') {
    $path = '';
}

if(!is_dir($path . $folder)) {
    die("Le dossier n'existe pas " . $path . $folder . "\n");
}

vlog("Démarrage de l'import");
importEtablissement($path . $folder);
importJours($path . $folder);
vlog("Fin de l'import");

$conn->close();

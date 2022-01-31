<?php

require './include/db.php';
require './include/import-functions.php';

function main(array $argv) {
    try {
        $configs = include('./include/config.php');
        
        if (!isset($argv)) {
            die;
        }
        
        $pdo = getNewPdo($configs['db']);
        
        $options = getopt('d:c:');
        
        $date = isset($options['d']) ? $options['d'] : null;
        $chemin = isset($options['c']) ? $options['c'] : $configs['importDir'];
        $folder = date('Y/m');
        $path = '';
        
        if ($date !== null) {
            $folder = $date;
        }
        
        if ($chemin !== null) {
            if (!is_dir($chemin)) {
                die("Le chemin précisé n'existe pas\n");
            }
        
            $path = is_dir($chemin) ? $chemin : $path;
        
            if (substr($path, -1) != '/') {
                $path .= '/';
            }
        }
        
        if ($path === '/') {
            $path = '';
        }
        
        if(!is_dir($path . $folder)) {
            die("Le dossier n'existe pas {$path}{$folder}\n");
        }
        
        vlog("Démarrage de l'import");
        importDataEtabs($pdo, $path.$folder);
        vlog("Fin de l'import");
        
        $pdo = null;
    } catch (PDOException $e) {
        die("Erreur PDO : ".$e->getMessage());
    } catch (Exception $e) {
        die("Erreur : ".$e->getMessage());
    }
}

main($argv);
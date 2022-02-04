<?php

require './include/db.php';
require './include/import-functions.php';

function main(array $argv): void {
    try {
        $configs = include('./include/config.php');
        
        if (!isset($argv)) {
            throw new Exception("Aucun argument spécifié");
        }
        
        $pdo = getNewPdo($configs['db']);
        
        $options = getopt('d:c:v');
        
        $date = isset($options['d']) ? $options['d'] : null;
        $chemin = isset($options['c']) ? $options['c'] : $configs['importDir'];
        $verbose = isset($options['v']);
        $folder = date('Y/m');
        $path = '';
        
        if ($date !== null) {
            $folder = $date;
        }
        
        if ($chemin !== null) {
            if (!is_dir($chemin)) {
                throw new Exception("Le chemin précisé n'existe pas");
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
            throw new Exception("Le dossier n'existe pas {$path}{$folder}");
        }
        
        vlog("Démarrage de l'import");
        importDataEtabs($pdo, $path.$folder, $verbose, $configs['env']);
        vlog("Fin de l'import");
        
        $pdo = null;
    } catch (PDOException $e) {
        die("Erreur PDO : ".$e->getMessage());
    } catch (Exception $e) {
        die("Erreur : ".$e->getMessage()."\n");
    }
}

main($argv);
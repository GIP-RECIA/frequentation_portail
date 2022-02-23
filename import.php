<?php

use App\Config;

require 'vendor/autoload.php';
require 'include/import-functions.php';

function main(array $argv): void {
    try {
        $config = Config::getInstance();
        
        if (!isset($argv)) {
            throw new Exception("Aucun argument spécifié");
        }
        
        $options = getopt('d:c:v');
        
        $date = isset($options['d']) ? $options['d'] : null;
        $chemin = isset($options['c']) ? $options['c'] : $config->get('importDir');
        $verbose = isset($options['v']);
        $yesterday = isset($options['y']);
        $folder = date('Y/m');
        $path = '';
        
        if ($date !== null) {
            $folder = $date;
        } elseif ($yesterday) {
            $folder = date('Y/m', time() - 24*60*60);
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
        importDataEtabs($path.$folder, $verbose, $config->get('env'));
        vlog("Fin de l'import");
        
    } catch (PDOException $e) {
        die("Erreur PDO : ".$e->getMessage());
    } catch (Exception $e) {
        die("Erreur : ".$e->getMessage()."\n");
    }
}

main($argv);
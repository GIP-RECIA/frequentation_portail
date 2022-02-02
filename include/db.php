<?php

/**
 * Créé un nouvel objet pdo
 *
 * @param array $conf La configuration pour la database
 *
 * @return Object L'objet pdo créé
 */
function getNewPdo($conf): Object {
    $pdo = new PDO(
        "mysql:host={$conf['host']};port={$conf['port']};dbname={$conf['dbName']};charset=utf8",
        $conf['user'],
        $conf['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $pdo;
}
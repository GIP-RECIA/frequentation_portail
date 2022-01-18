<?php

$arrIdServiceFromName = [];

/**
 * Import des données en bdd pour tous les établissements
 *
 * @param string $folder Le dossier des imports
 */
function importDataEtabs($folder) {
    global $conn;

    loadServices();

    $ex = explode("/", $folder);
    $length = (count($ex));

    if($length > 2) {
        $year = $ex[$length -2];
        $month = end($ex);
    } else {
        $year = $ex[0];
        $month = $ex[1];
    }

    if(!is_numeric($year)) {
        die("Erreur sur l'année récupérée ($year)");
    }

    if(!is_numeric($month)) {
        die("Erreur sur le mois récupéré ($month)");
    }

    $xml = simplexml_load_file($folder . '/liste_etablissements.xml');
    $etabs = $xml->xpath('/Etablissements/Etablissement');

    $sql = "DELETE FROM stats_etabs WHERE annee = '{$year}' and mois = '{$month}'";
    $res = mysqli_query($conn, $sql);
    $sql = "DELETE FROM stats_services WHERE annee = '{$year}' and mois = '{$month}'";
    $res = mysqli_query($conn, $sql);
    $arrEtabs = [];

    foreach ($etabs as $etab) {
        $id = null;
        $sql = "SELECT COUNT(siren) FROM etablissements WHERE siren = '{$etab['siren']}'";
        $res = mysqli_query($conn, $sql);

        // Si l'établissement existe déjà, on le met à jour, sinon on l'insert
        if (mysqli_fetch_array($res)[0] > 0) {
            $sql = "UPDATE etablissements SET nom = '{$etab['name']}', departement = '{$etab['departement']}', type = '{$etab['type']}' WHERE siren = '{$etab['siren']}'";
            $conn->query($sql);

            $sql = "SELECT id FROM etablissements WHERE siren = '{$etab['siren']}'";
            $id = null;

            if ($res = mysqli_query($conn, $sql)) {
                if ($row = mysqli_fetch_array($res)) {
                    $id = $row[0];
                }
            }

            mysqli_free_result($res);
        } else {
            $sql = "INSERT INTO etablissements (nom, departement, siren, type) VALUES ('{$etab['name']}', '{$etab['departement']}', '{$etab['siren']}', '{$etab['type']}')";
            $conn->query($sql);
            $id = $conn->insert_id;
        }

        if($id === null) {
            die("Erreur impossible de récupérer l'établissement ayant le siren {$etab['siren']}");
        }

        $arrEtabs[] = [
            'id' => $id,
            'siren' => $etab['siren'],
            'name' => $etab['name']
        ];
        $sql = "INSERT INTO stats_etabs
            (mois, annee, id_lycee, au_plus_quatre_fois, au_moins_cinq_fois, total_sessions, differents_users, total_pers_active)
            VALUES ('{$month}', '{$year}', '{$id}', '{$etab->AuPlus4Fois}', '{$etab->AuMoins5Fois}',
            '{$etab->TotalSessions}', '{$etab->DifferentsUsers}', '{$etab->TotalPersActive}')";
        $conn->query($sql);
    }

    $sql = "INSERT INTO stats_services (
        mois, annee, id_lycee, id_service,
        parent__au_plus_quatre_fois, parent__au_moins_cinq_fois, parent__total_sessions, parent__differents_users,
        eleve__au_plus_quatre_fois, eleve__au_moins_cinq_fois, eleve__total_sessions, eleve__differents_users,
        enseignant__au_plus_quatre_fois, enseignant__au_moins_cinq_fois, enseignant__total_sessions, enseignant__differents_users,
        perso_etab_non_ens__au_plus_quatre_fois, perso_etab_non_ens__au_moins_cinq_fois, perso_etab_non_ens__total_sessions, perso_etab_non_ens__differents_users,
        perso_collec__au_plus_quatre_fois, perso_collec__au_moins_cinq_fois, perso_collec__total_sessions, perso_collec__differents_users,
        tuteur_stage__au_plus_quatre_fois, tuteur_stage__au_moins_cinq_fois, tuteur_stage__total_sessions, tuteur_stage__differents_users
    ) VALUES (
        ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?, ?
    )";
    $stmtService = $conn->prepare($sql);

    if ($stmtService === false) {
        die("Erreur lors de la préparation de la requête 2");
    }

    $sql = "INSERT INTO stats_services (
        mois, annee, id_lycee,
        parent__total_pers_actives, parent__au_plus_quatre_fois, parent__au_moins_cinq_fois,
            parent__total_sessions, parent__differents_users, parent__tps_moyen_minutes,
        eleve__total_pers_actives, eleve__au_plus_quatre_fois, eleve__au_moins_cinq_fois,
            eleve__total_sessions, eleve__differents_users, eleve__tps_moyen_minutes,
        enseignant__total_pers_actives, enseignant__au_plus_quatre_fois, enseignant__au_moins_cinq_fois,
            enseignant__total_sessions, enseignant__differents_users, enseignant__tps_moyen_minutes,
        perso_etab_non_ens__total_pers_actives, perso_etab_non_ens__au_plus_quatre_fois, perso_etab_non_ens__au_moins_cinq_fois,
            perso_etab_non_ens__total_sessions, perso_etab_non_ens__differents_users, perso_etab_non_ens__tps_moyen_minutes,
        perso_collec__total_pers_actives, perso_collec__au_plus_quatre_fois, perso_collec__au_moins_cinq_fois,
            perso_collec__total_sessions, perso_collec__differents_users, perso_collec__tps_moyen_minutes,
        tuteur_stage__total_pers_actives, tuteur_stage__au_plus_quatre_fois, tuteur_stage__au_moins_cinq_fois,
            tuteur_stage__total_sessions, tuteur_stage__differents_users, tuteur_stage__tps_moyen_minutes
    ) VALUES (
        ?, ?, ?,
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?
    )";
    $stmtProfilsGlobaux = $conn->prepare($sql);

    if ($stmtProfilsGlobaux === false) {
        die("Erreur lors de la préparation de la requête 2");
    }

    foreach ($arrEtabs as $etab) {
        if (file_exists("{$folder}/mois_{$etab['siren']}.xml")) {
            vlog("Etablissement {$etab['name']}");
            importDataEtab($etab, "{$folder}/mois_{$etab['siren']}.xml", $month, $year, $stmtService, $stmtProfilsGlobaux);
        }
    }

    $stmtService->close();
    $stmtProfilsGlobaux->close();
}

/**
 * Import des données en bdd pour un établissement
 *
 * @param array $etab L'établissement
 * @param string $f Le nom du fichier correspondant à l'établissement
 * @param string $month Le mois
 * @param string $year L'année
 * @param object $stmtService Une requête d'insertion préparée pour les donnée d'un service
 * @param object $stmtProfilsGlobaux Une requête d'insertion préparée pour les donnée de profils globaux
 */
function importDataEtab($etab, $f, $month, $year, $stmtService, $stmtProfilsGlobaux) {
    global $conn;
    $xml = simplexml_load_file($f);
    $profils = $xml->xpath('/Etablissement/ProfilsGlobaux/ProfilGlobal');
    $users = [];

    foreach ($profils as $profil) {
        $users[(string)$profil['name']] = $profil;
    }

    $etablissement = $xml->xpath('/Etablissement');
    $stmtProfilsGlobaux->bind_param("iiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiii",
        $month, $year, $etab['id'],
        $users['Parent']->TotalPersActives, $users['Parent']->AuPlus4Fois, $users['Parent']->AuMoins5Fois,
            $users['Parent']->TotalSessions, $users['Parent']->DifferentsUsers, $users['Parent']->TpsMoyenMinutes,
        $users['Elève']->TotalPersActives, $users['Elève']->AuPlus4Fois, $users['Elève']->AuMoins5Fois,
            $users['Elève']->TotalSessions, $users['Elève']->DifferentsUsers, $users['Elève']->TpsMoyenMinutes,
        $users['Enseignant']->TotalPersActives, $users['Enseignant']->AuPlus4Fois, $users['Enseignant']->AuMoins5Fois,
            $users['Enseignant']->TotalSessions, $users['Enseignant']->DifferentsUsers, $users['Enseignant']->TpsMoyenMinutes,
        $users["Personnel d'établissement non enseignant"]->TotalPersActives, $users["Personnel d'établissement non enseignant"]->AuPlus4Fois, $users["Personnel d'établissement non enseignant"]->AuMoins5Fois,
            $users["Personnel d'établissement non enseignant"]->TotalSessions, $users["Personnel d'établissement non enseignant"]->DifferentsUsers, $users["Personnel d'établissement non enseignant"]->TpsMoyenMinutes,
        $users['Personnel de collectivité']->TotalPersActives, $users['Personnel de collectivité']->AuPlus4Fois, $users['Personnel de collectivité']->AuMoins5Fois,
            $users['Personnel de collectivité']->TotalSessions, $users['Personnel de collectivité']->DifferentsUsers, $users['Personnel de collectivité']->TpsMoyenMinutes,
        $users['Tuteur de stage']->TotalPersActives, $users['Tuteur de stage']->AuPlus4Fois, $users['Tuteur de stage']->AuMoins5Fois,
            $users['Tuteur de stage']->TotalSessions, $users['Tuteur de stage']->DifferentsUsers, $users['Tuteur de stage']->TpsMoyenMinutes);
    $stmtProfilsGlobaux->execute();
    //echo "ajout d'une ligne stats_services pour un profil global";

    $services = $xml->xpath('/Etablissement/Services/Service');

    foreach ($services as $service) {
        $idService = getIdServiceFromName($service['name']);
        $users = [];
        $profils = $service->xpath('Profils/Profil');


        foreach ($profils as $profil) {
            $users[(string)$profil['name']] = $profil;
        }

        $stmtService->bind_param("iiiiiiiiiiiiiiiiiiiiiiiiiiii", $month, $year, $etab['id'], $idService,
            $users['Parent']->AuPlus4Fois, $users['Parent']->AuMoins5Fois,
                $users['Parent']->TotalSessions, $users['Parent']->DifferentsUsers,
            $users['Elève']->AuPlus4Fois, $users['Elève']->AuMoins5Fois,
                $users['Elève']->TotalSessions, $users['Elève']->DifferentsUsers,
            $users['Enseignant']->AuPlus4Fois, $users['Enseignant']->AuMoins5Fois,
                $users['Enseignant']->TotalSessions, $users['Enseignant']->DifferentsUsers,
            $users["Personnel d'établissement non enseignant"]->AuPlus4Fois, $users["Personnel d'établissement non enseignant"]->AuMoins5Fois,
                $users["Personnel d'établissement non enseignant"]->TotalSessions, $users["Personnel d'établissement non enseignant"]->DifferentsUsers,
            $users['Personnel de collectivité']->AuPlus4Fois, $users['Personnel de collectivité']->AuMoins5Fois,
                $users['Personnel de collectivité']->TotalSessions, $users['Personnel de collectivité']->DifferentsUsers,
            $users['Tuteur de stage']->AuPlus4Fois, $users['Tuteur de stage']->AuMoins5Fois,
                $users['Tuteur de stage']->TotalSessions, $users['Tuteur de stage']->DifferentsUsers);
        $stmtService->execute();
    }
}

function loadServices() {
    global $conn, $arrIdServiceFromName;
    $arrIdServiceFromName = [];

    if ($res = mysqli_query($conn, "SELECT id, nom FROM services WHERE")) {
        while ($row = mysqli_fetch_array($res)) {
            $arrIdServiceFromName[$row['nom']] = $row['id'];
        }
        mysqli_free_result($res);
    }
}

function getIdServiceFromName($serviceName) {
    global $conn, $arrIdServiceFromName;
    $serviceName = addslashes($serviceName);

    if (array_key_exists($serviceName, $arrIdServiceFromName)) {
        return $arrIdServiceFromName[$serviceName];
    }

    $result = mysqli_query($conn, "SELECT id FROM services WHERE nom = '{$serviceName}'");
    $row = mysqli_fetch_assoc($result);

    if ($row) {
        $idService = $row['id'];
    } else {
        $sql = "INSERT INTO services (nom) VALUES ('{$serviceName}')";
        $conn->query($sql);
        $idService = $conn->insert_id;
    }

    mysqli_free_result($result);
    $arrIdServiceFromName[$serviceName] = $idService;

    return $idService;
}

function vlog($s) {
    echo $s."\n";
}
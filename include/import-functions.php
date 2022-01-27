<?php

/**
 * Import des données en bdd pour tous les établissements
 *
 * @param Object $pdo    L'objet pdo
 * @param string $folder Le dossier des imports
 */
function importDataEtabs(Object &$pdo, string $folder) {
    $arrIdServiceFromName = loadServices($pdo);

    $ex = explode("/", $folder);
    $length = (count($ex));

    if($length > 2) {
        $annee = $ex[$length -2];
        $mois = end($ex);
    } else {
        $annee = $ex[0];
        $mois = $ex[1];
    }

    if(!is_numeric($annee)) {
        die("Erreur sur l'année récupérée ($annee)");
    }

    if(!is_numeric($mois)) {
        die("Erreur sur le mois récupéré ($mois)");
    }

    $annee = intval($annee);
    $mois = intval($mois);
    $xml = simplexml_load_file($folder . '/liste_etablissements.xml');
    $etabs = $xml->xpath('/Etablissements/Etablissement');
    $req = $pdo->prepare("DELETE FROM stats_etabs WHERE annee = :annee and mois = :mois");
    $req->execute(['annee' => $annee, 'mois' => $mois]);
    $req = $pdo->prepare("DELETE FROM stats_services WHERE annee = :annee and mois = :mois");
    $req->execute(['annee' => $annee, 'mois' => $mois]);
    $arrEtabs = [];
    $reqEtabBySiren = $pdo->prepare("SELECT id FROM etablissements WHERE siren = :siren");
    $reqUpdateEtab = $pdo->prepare("UPDATE etablissements SET nom = :name, departement = :departement, type = :type WHERE id = :id");
    $reqInsertEtab = $pdo->prepare("INSERT INTO etablissements (nom, departement, siren, type) VALUES (:name, :departement, :siren, :type)");

    foreach ($etabs as $etab) {
        $etab = current($etab->attributes());
        $id = null;
        $reqEtabBySiren->execute(['siren' => $etab['siren']]);

        // Si l'établissement existe déjà, on le met à jour, sinon on l'insert
        if ($row = $reqEtabBySiren->fetch()) {
            $id = $row['id'];
            $reqUpdateEtab->execute(array_merge(array_diff_key($etab, ['siren' => null]), ['id' => $id]));
        } else {
            $reqInsertEtab->execute($etab);
            $id = $pdo->lastInsertId();
        }

        if($id === null) {
            die("Erreur impossible de récupérer l'établissement ayant le siren {$etab['siren']}");
        }

        $arrEtabs[] = [
            'id' => $id,
            'siren' => $etab['siren'],
            'name' => $etab['name']
        ];
    }

    $sql = "INSERT INTO stats_services (
        mois, annee, id_lycee, id_service,
        parent__au_plus_quatre_fois, parent__au_moins_cinq_fois, parent__total_sessions, parent__differents_users,
        eleve__au_plus_quatre_fois, eleve__au_moins_cinq_fois, eleve__total_sessions, eleve__differents_users,
        enseignant__au_plus_quatre_fois, enseignant__au_moins_cinq_fois, enseignant__total_sessions, enseignant__differents_users,
        perso_etab_non_ens__au_plus_quatre_fois, perso_etab_non_ens__au_moins_cinq_fois, perso_etab_non_ens__total_sessions, perso_etab_non_ens__differents_users,
        perso_collec__au_plus_quatre_fois, perso_collec__au_moins_cinq_fois, perso_collec__total_sessions, perso_collec__differents_users,
        tuteur_stage__au_plus_quatre_fois, tuteur_stage__au_moins_cinq_fois, tuteur_stage__total_sessions, tuteur_stage__differents_users,
        au_plus_quatre_fois, au_moins_cinq_fois, total_sessions, differents_users
    ) VALUES (
        ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?, ?
    )";
    $reqInsertService = $pdo->prepare($sql);

    if ($reqInsertService === false) {
        die("Erreur lors de la préparation de la requête sur le service");
    }

    $sql = "INSERT INTO stats_etabs (
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
            tuteur_stage__total_sessions, tuteur_stage__differents_users, tuteur_stage__tps_moyen_minutes,
        total_pers_actives, au_plus_quatre_fois, au_moins_cinq_fois, total_sessions, differents_users, tps_moyen_minutes
    ) VALUES (
        ?, ?, ?,
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?
    )";
    $reqInsertEtab = $pdo->prepare($sql);

    if ($reqInsertEtab === false) {
        die("Erreur lors de la préparation de la requête sur l'établissement");
    }

    foreach ($arrEtabs as $etab) {
        if (file_exists("{$folder}/mois_{$etab['siren']}.xml")) {
            vlog("Etablissement {$etab['name']}");
            importDataEtab($pdo, $arrIdServiceFromName, $etab, "{$folder}/mois_{$etab['siren']}.xml", $mois, $annee, $reqInsertService, $reqInsertEtab);
        }
    }
}

/**
 * Import des données en bdd pour un établissement
 *
 * @param Object $pdo                  L'objet pdo
 * @param array  $arrIdServiceFromName Le cache de relation name => id
 * @param array  $etab                 L'établissement
 * @param string $f                    Le nom du fichier correspondant à l'établissement
 * @param string $mois                 Le mois
 * @param string $annee                L'année
 * @param Object $reqInsertService          Une requête d'insertion préparée pour les donnée d'un service
 * @param Object $reqInsertEtab             Une requête d'insertion préparée pour les donnée de profils globaux
 */
function importDataEtab(Object &$pdo, array &$arrIdServiceFromName, $etab, $f, $mois, $annee, &$reqInsertService, &$reqInsertEtab) {
    $xml = simplexml_load_file($f);
    $profils = $xml->xpath('/Etablissement/ProfilsGlobaux/ProfilGlobal');
    $users = [];

    foreach ($profils as $profil) {
        $users[(string)$profil['name']] = $profil;
    }

    $etablissement = $xml->xpath('/Etablissement')[0];
    $reqInsertEtab->execute([$mois, $annee, $etab['id'],
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
            $users['Tuteur de stage']->TotalSessions, $users['Tuteur de stage']->DifferentsUsers, $users['Tuteur de stage']->TpsMoyenMinutes,
        $etablissement->TotalPersActives, $etablissement->AuPlus4Fois, $etablissement->AuMoins5Fois,
            $etablissement->TotalSessions, $etablissement->DifferentsUsers, $etablissement->TpsMoyenMinutes
    ]);

    $services = $xml->xpath('/Etablissement/Services/Service');

    foreach ($services as $service) {
        $idService = getIdServiceFromName($pdo, $arrIdServiceFromName, $service['name']);
        $users = [];
        $profils = $service->xpath('Profils/Profil');


        foreach ($profils as $profil) {
            $users[(string)$profil['name']] = $profil;
        }

        $reqInsertService->execute([$mois, $annee, $etab['id'], $idService,
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
                $users['Tuteur de stage']->TotalSessions, $users['Tuteur de stage']->DifferentsUsers,
            $service->AuPlus4Fois, $service->AuMoins5Fois, $service->TotalSessions, $service->DifferentsUsers
        ]);
    }
}

/**
 * Génère la liste de tous les services
 *
 * @param Object $pdo L'objet pdo
 *
 * @param array La liste de tous les services
 */
function loadServices(Object &$pdo) {
    $req = $pdo->prepare("SELECT id, nom FROM services WHERE");
    $req->execute();
    $arrIdServiceFromName = [];

    while ($row = $req->fetch()) {
        $arrIdServiceFromName[$row['nom']] = intval($row['id']);
    }

    return $arrIdServiceFromName;
}

/**
 * Récupère l'identifiant d'un service a partir de son nom
 *
 * @param Object $pdo                  L'objet pdo
 * @param array  $arrIdServiceFromName Le cache de relation name => id
 *
 * @return int L'identifiant du service
 */
function getIdServiceFromName(Object &$pdo, array &$arrIdServiceFromName, $serviceName) {
    $serviceName = addslashes($serviceName);

    if (array_key_exists($serviceName, $arrIdServiceFromName)) {
        return $arrIdServiceFromName[$serviceName];
    }

    $req = $pdo->prepare("SELECT id FROM services WHERE nom = :nom");
    $req->execute(['nom' => $serviceName]);

    if ($row = $req->fetch()) {
        $idService = $row['id'];
    } else {

    }

    if ($row) {
        $idService = $row['id'];
    } else {
        $req = $pdo->prepare("INSERT INTO services (nom) VALUES (:nom)");
        $req->execute(['nom' => $serviceName]);
        $idService = $pdo->lastInsertId();
    }

    $arrIdServiceFromName[$serviceName] = intval($idService);

    return $idService;
}

/**
 * Affichage dans la console
 *
 * @param string $s Le message à afficher
 */
function vlog(string $s) {
    echo $s."\n";
}
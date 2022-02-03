<?php

const PARENT = "Parent";
const ELEVE = "Elève";
const ENSEIGNANT = "Enseignant";
const PERS_NON_ENS = "Personnel d'établissement non enseignant";
const PERS_COL = "Personnel de collectivité";
const TUTEUR = "Tuteur de stage";

/**
 * Import des données en bdd pour tous les établissements
 *
 * @param Object $pdo     L'objet pdo
 * @param string $folder  Le dossier des imports
 * @param bool   $verbose Log verbeux ou non
 */
function importDataEtabs(Object &$pdo, string $folder, bool $verbose): void {
    $arrIdServiceFromName = loadServices($pdo);
    $arrTypes = loadTypes($pdo);
    $reverseArrTypes = array_flip($arrTypes);

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
        throw new Exception("Erreur sur l'année récupérée ($annee)");
    }

    if(!is_numeric($mois)) {
        throw new Exception("Erreur sur le mois récupéré ($mois)");
    }

    $arrMois = ['mois' => intval($mois), 'annee' => intval($annee)];
    $xml1 = simplexml_load_file($folder . '/liste_etablissements.xml');
    $etabs1 = $xml1->xpath('/Etablissements/Etablissement');
    $xml2 = simplexml_load_file($folder . '/etablissements_etat_lieux.xml');
    $etabs2 = $xml2->xpath('/Etablissements/Etablissement');
    // Effacement en cascade du mois, des stats_etabs du mois et des stats_services du mois
    $req = $pdo->prepare("DELETE FROM mois WHERE annee = :annee and mois = :mois");
    $req->execute($arrMois);
    // Effacement des données qui n'ont plus de liens
    // Effacer les etablissements qui ne sont plus liées a aucun stats_etabs et stats_services
    $sql = "DELETE FROM etablissements ";
    $sql .= "WHERE id NOT IN (SELECT DISTINCT id_etablissement FROM stats_etabs)";
    $sql .= " AND id NOT IN (SELECT DISTINCT id_etablissement FROM stats_services); ";
    // Effacer les services qui ne sont plus liées a aucun stats_services
    $sql .= "DELETE FROM services WHERE id NOT IN (SELECT DISTINCT id_service FROM stats_services)";
    // Effacer les types qui ne sont plus liées a aucun etab
    $sql .= "DELETE FROM types WHERE id NOT IN (SELECT DISTINCT id_type FROM etablissements);";
    $req = $pdo->prepare($sql);
    $req->execute();
    // Insertion du couple mois année
    $req = $pdo->prepare("INSERT INTO mois (mois, annee) VALUES (:mois, :annee)");
    $req->execute($arrMois);
    $idMois = intval($pdo->lastInsertId());
    $arrEtabs = [];
    $reqGetEtab = $pdo->prepare("SELECT id FROM etablissements WHERE nom = :name AND departement = :departement AND id_type = :id_type AND siren = :siren");
    $reqInsertEtab = $pdo->prepare("INSERT INTO etablissements (nom, departement, siren, id_type) VALUES (:name, :departement, :siren, :id_type)");

    // Première boucle pour gérer les informations des établissements
    foreach ($etabs1 as $etab) {
        $etab = current($etab->attributes());

        // Si ce type n'existe pas, on le créé
        if (!array_key_exists($etab['type'], $reverseArrTypes)) {
            $req = $pdo->prepare("INSERT INTO types (nom) VALUES (:nom)");
            $req->execute(['nom' => $etab['type']]);
            $id = intval($pdo->lastInsertId());
            $arrTypes[$id] = $etab['type'];
            $reverseArrTypes[$etab['type']] = $id;
        }

        $etab['id_type'] = $reverseArrTypes[$etab['type']];
        unset($etab['type']);
        $id = null;
        $reqGetEtab->execute($etab);

        // Si l'établissement existe déjà, on récupère son id, sinon on l'insère
        if ($row = $reqGetEtab->fetch(PDO::FETCH_ASSOC)) {
            $id = $row['id'];
        } else {
            // TODO: ajouter le traitement pour l'UAI
            $reqInsertEtab->execute($etab);
            $id = $pdo->lastInsertId();
        }

        if($id === null) {
            throw new Exception("Erreur impossible de récupérer l'établissement ayant le siren {$etab['siren']}");
        }

        $arrEtabs[$etab['siren']] = array_merge($etab, ['id' => $id]);
    }

    // seconde boucle qui permet d'ajouter les stats du nombre de comptes pendant le mois aux établissements
    foreach ($etabs2 as $etab) {
        $etabAttr = current($etab->attributes());

        if (!array_key_exists($etabAttr['siren'], $arrEtabs)) {
            vlog("WARNING : Établissement ignoré car absent du fichier liste_etablissements.xml : {$etab['siren']} - {$etab['name']}");
        } else {
            $users = [];
    
            foreach ($etab->ProfilsGlobaux->ProfilGlobal as $profil) {
                $users[(string)$profil['name']] = $profil;
            }
            
            $arrEtabs[$etabAttr['siren']]['users'] = $users;
        }
    }

    foreach ($arrEtabs as $etab) {
        if (!array_key_exists('users', $etab)) {
            vlog("WARNING : Établissement ignoré car absent du fichier etablissements_etat_lieux.xml : {$etab['siren']} - {$etab['name']}");
            unset($arrEtabs[$etab['siren']]);
        }
    }

    $sql = "INSERT INTO stats_services (
        id_mois, id_etablissement, id_service,
        parent__au_plus_quatre_fois, parent__au_moins_cinq_fois, parent__total_sessions, parent__differents_users,
        eleve__au_plus_quatre_fois, eleve__au_moins_cinq_fois, eleve__total_sessions, eleve__differents_users,
        enseignant__au_plus_quatre_fois, enseignant__au_moins_cinq_fois, enseignant__total_sessions, enseignant__differents_users,
        perso_etab_non_ens__au_plus_quatre_fois, perso_etab_non_ens__au_moins_cinq_fois, perso_etab_non_ens__total_sessions, perso_etab_non_ens__differents_users,
        perso_collec__au_plus_quatre_fois, perso_collec__au_moins_cinq_fois, perso_collec__total_sessions, perso_collec__differents_users,
        tuteur_stage__au_plus_quatre_fois, tuteur_stage__au_moins_cinq_fois, tuteur_stage__total_sessions, tuteur_stage__differents_users,
        au_plus_quatre_fois, au_moins_cinq_fois, total_sessions, differents_users
    ) VALUES (
        ?, ?, ?,
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
        throw new Exception("Erreur lors de la préparation de la requête sur le service");
    }

    $sql = "INSERT INTO stats_etabs (
        id_mois, id_etablissement,
        parent__total_pers, parent__total_pers_actives,
            parent__au_plus_quatre_fois, parent__au_moins_cinq_fois,
            parent__total_sessions, parent__differents_users, parent__tps_moyen_minutes,
        eleve__total_pers, eleve__total_pers_actives,
            eleve__au_plus_quatre_fois, eleve__au_moins_cinq_fois,
            eleve__total_sessions, eleve__differents_users, eleve__tps_moyen_minutes,
        enseignant__total_pers, enseignant__total_pers_actives,
            enseignant__au_plus_quatre_fois, enseignant__au_moins_cinq_fois,
            enseignant__total_sessions, enseignant__differents_users, enseignant__tps_moyen_minutes,
        perso_etab_non_ens__total_pers, perso_etab_non_ens__total_pers_actives,
            perso_etab_non_ens__au_plus_quatre_fois, perso_etab_non_ens__au_moins_cinq_fois,
            perso_etab_non_ens__total_sessions, perso_etab_non_ens__differents_users, perso_etab_non_ens__tps_moyen_minutes,
        perso_collec__total_pers, perso_collec__total_pers_actives,
            perso_collec__au_plus_quatre_fois, perso_collec__au_moins_cinq_fois,
            perso_collec__total_sessions, perso_collec__differents_users, perso_collec__tps_moyen_minutes,
        tuteur_stage__total_pers, tuteur_stage__total_pers_actives,
            tuteur_stage__au_plus_quatre_fois, tuteur_stage__au_moins_cinq_fois,
            tuteur_stage__total_sessions, tuteur_stage__differents_users, tuteur_stage__tps_moyen_minutes,
        total_pers_actives, au_plus_quatre_fois, au_moins_cinq_fois, total_sessions, differents_users, tps_moyen_minutes
    ) VALUES (
        ?, ?,
        ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?
    )";
    $reqInsertEtab = $pdo->prepare($sql);

    if ($reqInsertEtab === false) {
        throw new Exception("Erreur lors de la préparation de la requête sur l'établissement");
    }

    foreach ($arrEtabs as $etab) {
        if (file_exists("{$folder}/mois_{$etab['siren']}.xml")) {
            if ($verbose) {
                vlog("Etablissement {$etab['name']}");
            }
            importDataEtab($pdo, $arrIdServiceFromName, $etab, "{$folder}/mois_{$etab['siren']}.xml", $idMois, $reqInsertService, $reqInsertEtab);
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
 * @param string $idMois               L'identifiant du couple mois/annee
 * @param Object $reqInsertService     Une requête d'insertion préparée pour les donnée d'un service
 * @param Object $reqInsertEtab        Une requête d'insertion préparée pour les donnée de profils globaux
 */
function importDataEtab(Object &$pdo, array &$arrIdServiceFromName, $etab, $f, $idMois, &$reqInsertService, &$reqInsertEtab): void {
    $xml = simplexml_load_file($f);
    $profils = $xml->xpath('/Etablissement/ProfilsGlobaux/ProfilGlobal');
    $users = [];

    foreach ($profils as $profil) {
        $users[(string)$profil['name']] = $profil;
    }

    $etablissement = $xml->xpath('/Etablissement')[0];
    $reqInsertEtab->execute([$idMois, $etab['id'],
        $etab['users'][PARENT]->TotalPers, $users[PARENT]->TotalPersActives,
            $users[PARENT]->AuPlus4Fois, $users[PARENT]->AuMoins5Fois,
            $users[PARENT]->TotalSessions, $users[PARENT]->DifferentsUsers, $users[PARENT]->TpsMoyenMinutes,
        $etab['users'][ELEVE]->TotalPers, $users[ELEVE]->TotalPersActives,
            $users[ELEVE]->AuPlus4Fois, $users[ELEVE]->AuMoins5Fois,
            $users[ELEVE]->TotalSessions, $users[ELEVE]->DifferentsUsers, $users[ELEVE]->TpsMoyenMinutes,
        $etab['users'][ENSEIGNANT]->TotalPers, $users[ENSEIGNANT]->TotalPersActives,
            $users[ENSEIGNANT]->AuPlus4Fois, $users[ENSEIGNANT]->AuMoins5Fois,
            $users[ENSEIGNANT]->TotalSessions, $users[ENSEIGNANT]->DifferentsUsers, $users[ENSEIGNANT]->TpsMoyenMinutes,
        $etab['users'][PERS_NON_ENS]->TotalPers, $users[PERS_NON_ENS]->TotalPersActives,
            $users[PERS_NON_ENS]->AuPlus4Fois, $users[PERS_NON_ENS]->AuMoins5Fois,
            $users[PERS_NON_ENS]->TotalSessions, $users[PERS_NON_ENS]->DifferentsUsers, $users[PERS_NON_ENS]->TpsMoyenMinutes,
        $etab['users'][PERS_COL]->TotalPers, $users[PERS_COL]->TotalPersActives,
            $users[PERS_COL]->AuPlus4Fois, $users[PERS_COL]->AuMoins5Fois,
            $users[PERS_COL]->TotalSessions, $users[PERS_COL]->DifferentsUsers, $users[PERS_COL]->TpsMoyenMinutes,
        $etab['users'][TUTEUR]->TotalPers, $users[TUTEUR]->TotalPersActives,
            $users[TUTEUR]->AuPlus4Fois, $users[TUTEUR]->AuMoins5Fois,
            $users[TUTEUR]->TotalSessions, $users[TUTEUR]->DifferentsUsers, $users[TUTEUR]->TpsMoyenMinutes,
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

        $reqInsertService->execute([$idMois, $etab['id'], $idService,
            $users[PARENT]->AuPlus4Fois, $users[PARENT]->AuMoins5Fois,
                $users[PARENT]->TotalSessions, $users[PARENT]->DifferentsUsers,
            $users[ELEVE]->AuPlus4Fois, $users[ELEVE]->AuMoins5Fois,
                $users[ELEVE]->TotalSessions, $users[ELEVE]->DifferentsUsers,
            $users[ENSEIGNANT]->AuPlus4Fois, $users[ENSEIGNANT]->AuMoins5Fois,
                $users[ENSEIGNANT]->TotalSessions, $users[ENSEIGNANT]->DifferentsUsers,
            $users[PERS_NON_ENS]->AuPlus4Fois, $users[PERS_NON_ENS]->AuMoins5Fois,
                $users[PERS_NON_ENS]->TotalSessions, $users[PERS_NON_ENS]->DifferentsUsers,
            $users[PERS_COL]->AuPlus4Fois, $users[PERS_COL]->AuMoins5Fois,
                $users[PERS_COL]->TotalSessions, $users[PERS_COL]->DifferentsUsers,
            $users[TUTEUR]->AuPlus4Fois, $users[TUTEUR]->AuMoins5Fois,
                $users[TUTEUR]->TotalSessions, $users[TUTEUR]->DifferentsUsers,
            $service->AuPlus4Fois, $service->AuMoins5Fois, $service->TotalSessions, $service->DifferentsUsers
        ]);
    }
}

/**
 * Génère la liste de tous les services
 *
 * @param Object $pdo L'objet pdo
 *
 * @return array Le tableau des services
 */
function loadServices(Object &$pdo): array {
    $req = $pdo->prepare("SELECT id, nom FROM services");
    $req->execute();
    $arrIdServiceFromName = [];

    while ($row = $req->fetch(PDO::FETCH_ASSOC)) {
        $arrIdServiceFromName[$row['nom']] = intval($row['id']);
    }

    return $arrIdServiceFromName;
}

/**
 * Génère la liste de tous les types
 *
 * @param Object $pdo L'objet pdo
 *
 * @return array Le tableau des types
 */
function loadTypes(Object &$pdo): array {
    $req = $pdo->prepare("SELECT id, nom FROM types");
    $req->execute();
    $arrTypes = [];

    while ($row = $req->fetch(PDO::FETCH_ASSOC)) {
        $arrTypes[intval($row['id'])] = $row['nom'];
    }

    return $arrTypes;
}

/**
 * Récupère l'identifiant d'un service a partir de son nom
 *
 * @param Object $pdo                  L'objet pdo
 * @param array  $arrIdServiceFromName Le cache de relation name => id
 *
 * @return int L'identifiant du service
 */
function getIdServiceFromName(Object &$pdo, array &$arrIdServiceFromName, $serviceName): int {
    $serviceName = addslashes($serviceName);

    if (array_key_exists($serviceName, $arrIdServiceFromName)) {
        return $arrIdServiceFromName[$serviceName];
    }

    $req = $pdo->prepare("SELECT id FROM services WHERE nom = :nom");
    $req->execute(['nom' => $serviceName]);

    if ($row = $req->fetch(PDO::FETCH_ASSOC)) {
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
function vlog(string $s): void {
    echo $s."\n";
}
<?php

$arrIdServiceFromName = [];

/**
 * Import des établissement en bdd
 *
 * @param string $folder Le dossier des imports
 */
function importEtablissement($folder) {
    global $conn;
    $xml = simplexml_load_file($folder . '/liste_etablissements.xml');
    $etabs = $xml->xpath('/Etablissements/Etablissement');

    foreach ($etabs as $etab) {
        $sql = "SELECT COUNT(siren) FROM etablissements WHERE siren = '{$etab['siren']}'";
        $res = mysqli_query($conn, $sql);

        // Si l'établissement existe déjà, on le met à jour, sinon on l'insert
        if (mysqli_fetch_array($res)[0] > 0) {
            $sql = "UPDATE etablissements SET nom = '{$etab['name']}', departement = '{$etab['departement']}', type = '{$etab['type']}', total_personnes = '{$etab->TotalPersActive}' WHERE siren = '{$etab['siren']}'";
        } else {
            $sql = "INSERT INTO etablissements (nom, departement, siren, type, total_personnes) VALUES ('{$etab['name']}', '{$etab['departement']}', '{$etab['siren']}', '{$etab['type']}', '{$etab->TotalPersActive}')";
        }

        $conn->query($sql);
    }
}

/**
 * Import des stats en bdd
 *
 * @param string $folder Le dossier des imports
 */
function importJours($folder) {
    global $conn;

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

    $sql = "DELETE FROM stats WHERE annee = '{$year}' and mois = '{$month}'";
    $res = mysqli_query($conn, $sql);

    $sql = "DELETE FROM stats_etab_mois WHERE annee = '{$year}' and mois = '{$month}'";
    $res = mysqli_query($conn, $sql);

    $sql = "INSERT INTO stats_etab_mois (
        jour, mois, annee, id_lycee, au_plus_quatre_fois, au_moins_cinq_fois, nb_visiteurs, total_visites, parent,
        eleve, enseignant, personnel_etablissement_non_enseignant, personnel_collectivite
    ) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt1 = $conn->prepare($sql);
    $sql = "INSERT INTO stats (
        jour, mois, annee, id_lycee, id_service, au_plus_quatre_fois, au_moins_cinq_fois, nb_visiteurs,
        total_visites, parent, eleve, enseignant, personnel_etablissement_non_enseignant,
        personnel_collectivite
    ) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt2 = $conn->prepare($sql);

    $sql = "SELECT nom, siren FROM etablissements";

    if ($res = mysqli_query($conn, $sql)) {
        while ($row = mysqli_fetch_array($res)) {
            $siren = $row['siren'];

            if (file_exists("{$folder}/mois_{$siren}.xml")) {
                vlog("Etablissement {$row['nom']}");
                importJoursFile($siren, "{$folder}/mois_{$siren}.xml", $month, $year, $stmt1, $stmt2);
            }
        }
        mysqli_free_result($res);
    }

    $stmt1->close();
    $stmt2->close();
}

function importJoursFile($siren, $f, $month, $year, $stmt1, $stmt2) {
    global $conn;
    $xml = simplexml_load_file($f);
    $result = mysqli_query($conn, "SELECT id FROM etablissements WHERE siren = {$siren}");
    $row = mysqli_fetch_assoc($result);
    $idEtab = $row['id'];

    $total_parent = 0;
    $total_eleve = 0;
    $total_enseignant = 0;
    $total_personnelNonEnseignant = 0;
    $total_personnelCollectivite = 0;

    $profils = $xml->xpath('/Etablissement/ProfilsGlobaux/ProfilGlobal');

    foreach ($profils as $profil) {
        switch ($profil['name']) {
            case 'Parent':
                $parent = $profil->DifferentsUsers;
                break;
            case 'Elève':
                $eleve = $profil->DifferentsUsers;
                break;
            case 'Enseignant':
                $enseignant = $profil->DifferentsUsers;
                break;
            case "Personnel d'établissement non enseignant":
                $personnelNonEnseignant = $profil->DifferentsUsers;
                break;
            case 'Personnel de collectivité':
                $personnelCollectivite = $profil->DifferentsUsers;
        }
    }

    $etablissement = $xml->xpath('/Etablissement');

    $stmt1->bind_param("iiiiiiiiiiii", $month, $year, $idEtab, $etablissement[0]->AuPlus4Fois,
        $etablissement[0]->AuMoins5Fois, $etablissement[0]->DifferentsUsers, $etablissement[0]->TotalSessions,
        $total_parent, $total_eleve, $total_enseignant, $total_personnelNonEnseignant, $total_personnelCollectivite);
    $stmt1->execute();

    $services = $xml->xpath('/Etablissement/Services/Service');

    foreach ($services as $service) {
        $idService = getIdServiceFromName($service['name']);
        $parent = 0;
        $eleve = 0;
        $enseignant = 0;
        $personnelNonEnseignant = 0;
        $personnelCollectivite = 0;

        $profils = $service->xpath('Profils/Profil');

        foreach ($profils as $profil) {
            switch ($profil['name']) {
                case 'Parent':
                    $parent = $profil->DifferentsUsers;
                    break;
                case 'Elève':
                    $eleve = $profil->DifferentsUsers;
                    break;
                case 'Enseignant':
                    $enseignant = $profil->DifferentsUsers;
                    break;
                case "Personnel d'établissement non enseignant":
                    $personnelNonEnseignant = $profil->DifferentsUsers;
                    break;
                case 'Personnel de collectivité':
                    $personnelCollectivite = $profil->DifferentsUsers;
            }
        }

        $stmt2->bind_param("iiiiiiiiiiiii", $month, $year, $idEtab, $idService, $service->AuPlus4Fois,
            $service->AuMoins5Fois, $service->DifferentsUsers, $service->TotalSessions, $parent, $eleve, $enseignant,
            $personnelNonEnseignant, $personnelCollectivite);
        $stmt2->execute();
    }

    

    mysqli_free_result($result);
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
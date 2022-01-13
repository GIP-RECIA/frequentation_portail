<?php

function importEtablissement($folder) {
    global $conn;
    $xml = simplexml_load_file($folder . '/liste_etablissements.xml');
    $etabs = $xml->xpath('/Etablissements/Etablissement');

    foreach ($etabs as $etab) {
        $sql = "INSERT INTO etablissements (nom, departement, siren, type, total_personnes) VALUES ('" . $etab['name'] . "', '" . $etab['departement'] . "', '" . $etab['siren'] . "', '" . $etab['type'] . "', '" . $etab->TotalPersActive . "')";
        $conn->query($sql);
    }
}

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

    $sql = "DELETE FROM stats WHERE annee = '" . $year . "' and mois = '" . $month . "'";
    $res = mysqli_query($conn, $sql);

    $sql = "DELETE FROM stats_etab_mois WHERE annee = '" . $year . "' and mois = '" . $month . "'";
    $res = mysqli_query($conn, $sql);

    $sql = "SELECT nom, siren FROM etablissements";

    if ($res = mysqli_query($conn, $sql)) {
        while ($row = mysqli_fetch_array($res)) {
            $siren = $row['siren'];

            if (file_exists($folder . '/mois_' . $siren . '.xml')) {
                vlog("Etablissement " . $row['nom']);
                importJoursFile($siren, $folder . '/mois_' . $siren . '.xml', $month, $year);
            }
        }
        mysqli_free_result($res);
    }
}

function importJoursFile($siren, $f, $month, $year) {
    global $conn;
    $xml = simplexml_load_file($f);
    $result = mysqli_query($conn, "SELECT id FROM etablissements WHERE siren = " . $siren);
    $row = mysqli_fetch_assoc($result);
    $idEtab = $row['id'];

    $total_parent = 0;
    $total_eleve = 0;
    $total_enseignant = 0;
    $total_personnelNonEnseignant = 0;
    $total_personnelCollectivite = 0;

    $profils = $xml->xpath('/Etablissement/ProfilsGlobaux/ProfilGlobal');

    foreach ($profils as $profil) {
        if ($profil['name'] == 'Parent')
            $total_parent = $profil->DifferentsUsers;
        if ($profil['name'] == 'Elève')
            $total_eleve = $profil->DifferentsUsers;
        if ($profil['name'] == 'Enseignant')
            $total_enseignant = $profil->DifferentsUsers;
        if ($profil['name'] == 'Personnel d\'établissement non enseignant')
            $total_personnelNonEnseignant = $profil->DifferentsUsers;
        if ($profil['name'] == 'Personnel de collectivité')
            $total_personnelCollectivite = $profil->DifferentsUsers;
    }

    $etablissement = $xml->xpath('/Etablissement');

    $sql = "INSERT INTO stats_etab_mois (
        jour,
        mois,
        annee,
        id_lycee,
        au_plus_quatre_fois,
        au_moins_cinq_fois,
        nb_visiteurs,
        total_visites,
        parent,
        eleve,
        enseignant,
        personnel_etablissement_non_enseignant,
        personnel_collectivite
    ) VALUES (
        " . "NULL" . ",
        '" . $month . "',
        '" . $year . "',
        '" . $idEtab . "',
        '" . $etablissement[0]->AuPlus4Fois . "',
        '" . $etablissement[0]->AuMoins5Fois . "',
        '" . $etablissement[0]->DifferentsUsers . "',
        '" . $etablissement[0]->TotalSessions . "',
        '" . $total_parent . "',
        '" . $total_eleve . "',
        '" . $total_enseignant . "',
        '" . $total_personnelNonEnseignant . "',
        '" . $total_personnelCollectivite . "'
    )";
    $conn->query($sql);

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
            if ($profil['name'] == 'Parent') {
                $parent = $profil->DifferentsUsers;
            }

            if ($profil['name'] == 'Elève') {
                $eleve = $profil->DifferentsUsers;
            }

            if ($profil['name'] == 'Enseignant') {
                $enseignant = $profil->DifferentsUsers;
            }

            if ($profil['name'] == 'Personnel d\'établissement non enseignant') {
                $personnelNonEnseignant = $profil->DifferentsUsers;
            }

            if ($profil['name'] == 'Personnel de collectivité') {
                $personnelCollectivite = $profil->DifferentsUsers;
            }
        }

        $sql = "INSERT INTO stats (
                jour,
                mois,
                annee,
                id_lycee,
                id_service,
                au_plus_quatre_fois,
                au_moins_cinq_fois,
                nb_visiteurs,
                total_visites,
                parent,
                eleve,
                enseignant,
                personnel_etablissement_non_enseignant,
                personnel_collectivite
            ) VALUES (
                " . "NULL" . ",
                '" . $month . "',
                '" . $year . "',
                '" . $idEtab . "',
                '" . $idService . "',
                '" . $service->AuPlus4Fois . "',
                '" . $service->AuMoins5Fois . "',
                '" . $service->DifferentsUsers . "',
                '" . $service->TotalSessions . "',
                '" . $parent . "',
                '" . $eleve . "',
                '" . $enseignant . "',
                '" . $personnelNonEnseignant . "',
                '" . $personnelCollectivite . "'
            )";
        $conn->query($sql);
    }

    mysqli_free_result($result);
}

function getIdServiceFromName($serviceName) {
    global $conn;
    $result = mysqli_query($conn, "SELECT id FROM services WHERE nom = '" . addslashes($serviceName) . "'");
    $row = mysqli_fetch_assoc($result);

    if ($row) {
        $idService = $row['id'];
    } else {
        $sql = "INSERT INTO services (nom) VALUES ('" . addslashes($serviceName) . "')";
        $conn->query($sql);
        $idService = $conn->insert_id;
    }

    mysqli_free_result($result);
    return $idService;
}

function vlog($s) {
    echo $s."\n";
}
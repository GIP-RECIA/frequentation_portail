<?php

/**
 * Initialisation de la connexion au CAS et force l'authentification
 *
 * @param array $configCas La configuration du cas
 */
function casInit(array $configCas) {
    $cas_host = $configCas["host"];
    $cas_port = $configCas["port"];
    $cas_context = $configCas["context"];
    $cas_server_ca_cert_path = $configCas["certificat"];
    
    $cas_reals_hosts = [$cas_host];
    //si uniquement tranmission attribut
    phpCAS::setDebug();
    phpCAS::setVerbose(true);
    
    phpCAS::client(CAS_VERSION_2_0, $cas_host, $cas_port, $cas_context);
    //phpCAS::client(SAML_VERSION_1_1, $cas_host, $cas_port, $cas_context);
    
    phpCAS::setCasServerCACert($cas_server_ca_cert_path);
    phpCAS::handleLogoutRequests(true, $cas_reals_hosts);
    phpCAS::forceAuthentication();
}

/**
 * Lance la déconnexion du CAS
 */
function casLogout() {
    phpCAS::logout();
}

/**
 * Récupère un attribut CAS dans la session
 *
 * @param string $attributeName Le nom de l'attribut a récupérer
 */
function getCasAttribute(string $attributeName) {
    return $_SESSION['phpCAS']['attributes'][$attributeName];
}
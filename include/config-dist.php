<?php

return [
    'db' => [
        'host' => "localhost",
        'port' => 3306,
        'dbName' => "frequentation_portail",
        'user' => "",
        'password' => "",
    ],
    'cas' => [
        'host' => "secure.giprecia.net",
        'port' => 443,
        'context' => "cas",
        'certificat' => "",
    ],
    'ldap' => [
        'protocole' => "ldap",
        'host' => "",
        'port' => 389,
        'dn' => "cn=stats,ou=administrateurs,dc=esco-centre,dc=fr",
        'password' => "",
    ],
    'importDir' => '',
    'env' => "prod",
    'departments' => [18, 28, 36, 37, 41, 45],
    /*'debug' => [
        'uid' => "f20u000a",
    ],*/
];
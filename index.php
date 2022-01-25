<?php

require './vendor/autoload.php';
require './include/web-functions.php';

$configs = include('./include/config.php');

$annuaireParams = $configs['cas'];

$cas_host = $annuaireParams["host"];
$cas_port = $annuaireParams["port"];
$cas_context = $annuaireParams["context"];
$cas_server_ca_cert_path = $annuaireParams["certificat"];

$cas_reals_hosts = [$cas_host];
//si uniquement tranmission attribut
phpCAS::setDebug();
phpCAS::setVerbose(true);

phpCAS::client(CAS_VERSION_2_0, $cas_host, $cas_port, $cas_context);
//phpCAS::client(SAML_VERSION_1_1, $cas_host, $cas_port, $cas_context);

phpCAS::setCasServerCACert($cas_server_ca_cert_path);
phpCAS::handleLogoutRequests(true, $cas_reals_hosts);
phpCAS::forceAuthentication();

if (isset($_REQUEST['logout'])) {
    phpCAS::logout();
}

$db = $configs['db'];
$conn = new mysqli($db['host'], $db['user'], $db['password'], $db['dbName'], $db['port']);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

//$_SESSION['phpCAS']['attributes']['ESCOSIRENCourant'] = "19450042700035"; //durzy
//unset($_SESSION['phpCAS']['attributes']['ESCOSIRENCourant']);
$siren = $_SESSION['phpCAS']['attributes']['ESCOSIRENCourant'];
//enseignant: National_ENS
//directeur: National_DIR
$role = $_SESSION['phpCAS']['attributes']['ENTPersonProfils'];
$etablissement = !empty($_SESSION['phpCAS']['attributes']['ESCOSIRENCourant']) ? get_etablissement_id_by_siren($siren) : null;
$etabReadOnly = $etablissement !== null ? true : false;
$show_simple_data = !empty($etablissement) && $role == "National_DIR";

if (!empty($etablissement)) {
    $_REQUEST["etab"] = $etablissement;
}

$mois = "-1";
$etab = "-1";
$resultType = "services";
$etabType = [];

$listMois = getListMois();

if (isset($_REQUEST)) {
    foreach ($_REQUEST as $key => $item) {
        if ($key === "etabType") {
            $elems = [];

            foreach ($item as $keyElem => $elem) {
                $elems[$keyElem] = mysqli_real_escape_string($conn, $elem);
            }

            $_REQUEST[$key] = $elems;
        } else {
            $_REQUEST[$key] = mysqli_real_escape_string($conn, $item);
        }
    }
}

if (isset($_REQUEST["etabType"]))
    $etabType = $_REQUEST["etabType"];

//$resultType = "etabs";
if (isset($_REQUEST["etab"]))
    $etab = $_REQUEST["etab"];

if (isset($_REQUEST["mois"]))
    $mois = $_REQUEST["mois"];

if (isset($_REQUEST["resultType"]))
    $resultType = $_REQUEST["resultType"];

if (isset($_REQUEST["resultId"])) {
    echo getStatsHTML($_REQUEST["resultId"]);
    die;
}

if (isset($_REQUEST["top"])) {
    echo getTopHTML($_REQUEST["serviceId"], $mois);
    die;
}

?>
<!doctype html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <title>Statistiques</title>

        <script src="./assets/js/jquery.min.js"></script>
        <script src="./assets/js/boostrap.min.js"></script>
        <script src="./assets/js/datatables.min.js"></script>
        <script src="./assets/js/select2.js"></script>
        <script src="./assets/js/datatables.buttons.min.js"></script>
        <script src="./assets/js/buttons_flash.js"></script>
        <script src="./assets/js/jszip.min.js"></script>
        <script src="./assets/js/buttons.html5.js"></script>
        <script src="./assets/js/button.print.js"></script>


        <link rel="stylesheet" href="./assets/css/bootstrap.css">
        <link rel="stylesheet" href="./assets/css/datatables.css">
        <link rel="stylesheet" href="./assets/css/datatables.responsive.css">
        <link rel="stylesheet" href="./assets/css/select2.css">
        <link rel="stylesheet" href="./assets/css/styles.css"/>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const checkbox = document.querySelector('input[name="switch1"]');

                checkbox.addEventListener('change', function () {
                    const result = document.getElementById("result");
                    if (checkbox.checked) {
                        result.classList.replace("population", "ratio");
                    } else {
                        result.classList.replace("ratio", "population");
                    }
                });
            });
        </script>
    </head>

    <body>
    <header>
        <div class="navbar navbar-dark bg-dark shadow-sm">
            <div class="container-fluid d-block">
                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarHeader"
                        aria-controls="navbarHeader" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <a class="navbar-brand" href="#"><img src="./images/logoNOC.svg" alt="Net O'Centre"></a>
            </div>
        </div>
    </header>
    <section id="chapeau">
        <div class="container-fluid">
            <div id="filters">
                <h1>Statistiques de fréquentation</h1>
                <h2>ENT Net O'Centre</h2>
                <?php if (!$show_simple_data): ?>
                    <form id="filters" action="" method="post" class="form-inline">
                        <div class="form-group mr-2 mb-3">
                            <label>Voir les résultats pour :</label>
                        </div>
                        <?php
                        if (!$etabReadOnly) {
                        ?>
                        <div class="form-group mr-2 mb-3">
                            <label for="etabType" class="sr-only">catégorie</label>
                            <select id="etabType" name="etabType[]" class="form-control js-select2-mutliple"
                                    multiple="multiple" style="width:300px;">
                                <?php

                                $types = getTypesEtablissements();
                                foreach ($types as $name) {
                                    echo "<option value=\"" . $name . "\"" . ((in_array($name, $etabType)) ? " selected " : "") . ">" . $name . "</option>";
                                }

                                ?>
                            </select>
                        </div>
                        <?php
                        }
                        ?>
                        <div class="form-group mr-2 mb-3">
                            <label for="etab" class="sr-only">établissement</label>
                            <select id="etab" name="etab" class="form-control">
                                <?php

                                $etabs = getEtablissements($etabType);

                                if (!$etabReadOnly) {
                                    echo '<option value="-1">Tous les établissements</option>';
                                }

                                foreach ($etabs as $id => $name) {
                                    if (!$etabReadOnly || $etab == $id) {
                                        echo "<option value=" . $id . " " . (($etab == $id) ? " selected " : "") . ">" . $name . "</option>";
                                    }
                                }

                                ?>
                            </select>
                        </div>
                        <div class="form-group mr-2 mb-3">
                            <label for="mois" class="sr-only">Période</label>
                            <select id="mois" name="mois" class="form-control">
                                <option value="-1">Tous les mois</option>
                                <?php
                                foreach ($listMois as $m) {
                                    echo '<option ' . (($m == $mois) ? " selected " : "") . ' value="' . $m . '">' . $m . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <button id="filterBtn" class="btn btn-primary mb-3">Filtrer</button>
                        <button id="reset" class="btn btn-default mb-3">Ré-initialiser les filtres</button>
                        <input id="resultType" type="hidden" name="resultType" value="<?= $resultType ?>"/>
                    </form>
                <?php endif; ?>
            </div>
            <div>
    </section>
    <section id="statistiques">
        <div class="container-fluid">
            <div class="row">
                <div class="col-6">
                    <?php if (!$show_simple_data): ?>
                        <div class="custom-control custom-radio custom-control-inline">
                            <input type="radio" id="vueservices" name="vue" class="custom-control-input"
                                   value="services" <?= (($resultType == 'services') ? 'checked' : '') ?> >
                            <label class="custom-control-label" for="vueservices">Vue Services</label>
                        </div>
                        <div class="custom-control custom-radio custom-control-inline">
                            <input type="radio" id="vuelycees" name="vue" class="custom-control-input"
                                   value="etabs" <?= (($resultType == 'etabs') ? 'checked' : '') ?> >
                            <label class="custom-control-label" for="vuelycees">Vue Etablissements</label>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-6">
                    <div class="custom-control custom-switch text-right mb-3">
                        <input type="checkbox" class="custom-control-input" id="customSwitch1" name="switch1">
                        <label class="custom-control-label" for="customSwitch1">Voir le ratio des visites par rapport aux
                            utilisateurs potentiels</label>
                    </div>
                </div>
            </div>
            <?php
            echo displayTable($etab);
            ?>
        </div>
    </section>
    <!-- Modal -->
    <div class="modal fade " id="topModal" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true">
        <div class="modal-dialog-centered modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="">Classement par établissement</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div id="topContent" class="modal-body">

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-primary" data-dismiss="modal">Fermer</button>
                </div>
            </div>

        </div>
    </div>
    </body>
    <script type="text/javascript">
        $( document ).ready(function() {
            jQuery.fn.dataTableExt.oSort["percent-asc"]  = function(x,y) {
                const xa = parseFloat(x.split("%")[0]);
                const ya = parseFloat(y.split("%")[0]);
                return ((xa < ya) ? -1 : ((xa > ya) ? 1 : 0));
            };

            jQuery.fn.dataTableExt.oSort["percent-desc"] = function(x,y) {
                return jQuery.fn.dataTableExt.oSort["percent-asc"](y, x);
            };

            const perType = { "sType": "percent" };

            $('.top20').click (function () {
                $.ajax({
                    url: "./index.php?top",
                    type: "POST",
                    async: false,
                    data: ({
                        serviceId: $(this).attr('data-serviceid'),
                        mois: $('#mois').val()
                    }),
                    complete: function(data){
                        $('#topContent').html(data.responseText);
                        console.log(data.responseText);
                        $('#topModal').modal('show');
                    }
                });
            });

            $('#result').DataTable({
                "paging": false,
                "ordering": true,
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'excelHtml5',
                        exportOptions: {
                            format: {
                                body: function (data, row, column, node) {
                                    if (column == 0) {
                                        return data.replace(/<\/?span[^>]*>/g,'').replace('TOP','');
                                    } else {
                                        return data.replace(/<br>/g,' - ');
                                    }
                                }
                            }
                        }
                    }
                ],
                "aoColumns": [
                    null, null, null, null, null,
                    null, null, null, null, null, null,
                    perType, perType, perType, perType, perType, perType,
                ]
            });

            $('input:radio[name="vue"]').change(function(){
                if ($(this).is(':checked')) {
                    $('#resultType').val($(this).val());
                    $('#filterBtn').click();
                }
            });

            $('#reset').click (function () {
                $('#etabType').val(null);
                $('#etab').val(-1);
                $('#mois').val(-1);
                $(location).attr('href','/');
            });

            $('#etab').select2({
                disabled: <?php echo $etabReadOnly ? 'true' : 'false'; ?>
            });

            // Mutliple select Etablissement
            $('.js-select2-mutliple').select2({
                placeholder: "Tous le types"
            });

        })
    </script>

</html>
<?php
$conn->close();
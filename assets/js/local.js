function storageAvailable(type) {
    try {
        var storage = window[type],
            x = '__storage_test__';
        storage.setItem(x, x);
        storage.removeItem(x);
        return true;
    }
    catch(e) {
        return e instanceof DOMException && (
            // everything except Firefox
            e.code === 22 ||
            // Firefox
            e.code === 1014 ||
            // test name field too, because code might not be present
            // everything except Firefox
            e.name === 'QuotaExceededError' ||
            // Firefox
            e.name === 'NS_ERROR_DOM_QUOTA_REACHED') &&
            // acknowledge QuotaExceededError only if there's something already stored
            storage.length !== 0;
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const table = document.getElementById("result");

    document.querySelectorAll('.switch-auto').forEach(
        (currentSwitch) => {
            if (storageAvailable('sessionStorage')) {
                let check = sessionStorage.getItem(currentSwitch.id);

                if (check == null) {
                    sessionStorage.setItem(currentSwitch.id, currentSwitch.checked);
                } else {
                    currentSwitch.checked = check == 'true';
                }
            }
            table.classList.add(currentSwitch.checked === true ? currentSwitch.dataset.val2 : currentSwitch.dataset.val1);
            currentSwitch.addEventListener('change', function() {
                const val1 = this.dataset.val1, val2 = this.dataset.val2;

                if (this.checked) {
                    table.classList.replace(val1, val2);
                } else {
                    table.classList.replace(val2, val1);
                }

                if (storageAvailable('sessionStorage')) {
                    sessionStorage.setItem(this.id, this.checked);
                }
            });
        }
    )

    document.querySelectorAll('.sel-col').forEach(
        elem => table.classList.add(elem.checked ? elem.dataset.val2 : elem.dataset.val1)
    )
});


$( document ).ready(function() {
    const $mois = $('#mois');
    const $departement = $('#departement');
    const $etabType = $('#etabType');
    const $etabType2 = $('#etabType2');
    const $etab = $('#etab');
    const formValues = {
        serviceId: null,
        mois: $mois.val(),
        departement: $departement.val(),
        etabType: $etabType.val(),
        etabType2: $etabType2.val()
    };
    const perType = { "sType": "percent" };
    let reload = true;

    jQuery.fn.dataTableExt.oSort["percent-asc"]  = function(x,y) {
        const xa = parseFloat(x.split("%")[0]);
        const ya = parseFloat(y.split("%")[0]);
        return ((xa < ya) ? -1 : ((xa > ya) ? 1 : 0));
    };

    jQuery.fn.dataTableExt.oSort["percent-desc"] = function(x,y) {
        return jQuery.fn.dataTableExt.oSort["percent-asc"](y, x);
    };

    $('.top20').click (function () {
        const serviceTitle = $(this).parent().contents().get(2).nodeValue.trim();
        formValues.serviceId = $(this).attr('data-serviceid');
        $.ajax({
            url: "./top.php",
            type: "POST",
            async: false,
            data: formValues,
            complete: function(data){
                $('#serviceTitle').html(serviceTitle);
                $('#topContent').html(data.responseText);
                $('#topModal').modal('show');
            }
        });
    });

    /**
     * Système de rechargement des listes dynamique
     */
    const reloadFilters = function (type) {
        if (reload) {
            $.ajax({
                url: "./reloadFilters.php",
                type: "POST",
                async: false,
                data: ({
                    mois: $mois.val(),
                    departement: $departement.val(),
                    etabType: $etabType.val(),
                    etabType2: $etabType2.val(),
                    pos: this.dataset.pos
                }),
                complete: function(data){
                    const dataDepartements = data.responseJSON['departements'];
                    const dataTypes = data.responseJSON['types'];
                    const dataTypes2 = data.responseJSON['types2'];
                    const dataEtabs = data.responseJSON['etabs'];

                    if (dataDepartements != undefined) {
                        const actualDepartement = $departement.val();
                        $departement.empty();
                        dataDepartements.forEach((val) => {
                            $departement.append(new Option(val, val, false, actualDepartement.includes(val.toString())))
                        });
                    }

                    if (dataTypes != undefined) {
                        const actualType = $etabType.val();
                        $etabType.empty();
                        dataTypes.forEach((val) => {
                            $etabType.append(new Option(val['nom'], val['id'], false, actualType.includes(val['id'])))
                        });
                    }

                    if (dataTypes2 != undefined) {
                        const actualType2 = $etabType2.val();
                        $etabType2.empty();
                        dataTypes2.forEach((val) => {
                            $etabType2.append(new Option(val['nom'], val['id'], false, actualType2.includes(val['id'])))
                        });
                    }

                    if (dataEtabs != undefined) {
                        const actualEtab = $etab.val();
                        $etab.empty().append(new Option("Tous les établissements", "-1", true, actualEtab == -1))
                        dataEtabs.forEach((val) => {
                            $etab.append(new Option(val['nom'], val['id'], false, actualEtab == val['id']))
                        });
                    }
                }
            });
        }
    };

    $mois.change(reloadFilters);
    $departement.change(reloadFilters);
    $etabType.change(reloadFilters);
    $etabType2.change(reloadFilters);

    $('#result').DataTable({
        autoWidth: false,
        paging: false,
        ordering: true,
        order: [[1, "asc"]],
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'excelHtml5',
                exportOptions: {
                    format: {
                        body: function (data, row, column, node) {
                            const startColumn = document.getElementById("result").classList.contains('view-service')? 0 : 1;
                            data = data.replace(/<\/?span[^>]*>[^>]*<\/span>/g,'').trim();

                            if (column > startColumn) {
                                return data.replace(/( |&nbsp;|<\/?i>)/g, '').replace(/<br>/g,' - ');
                            }

                            return data;
                        }
                    },
                    columns: ':visible'
                }
            }
        ],
        columnDefs: [
            {
                targets: "_all",
                className: 'dt-body-right'
            }, {
                targets: [2,3,4,5, 6,7,8,9,10,11],
                render: $.fn.dataTable.render.number(' ', '.', 0, '')
            }
        ],
        aoColumns: [
            null, null, null, null, null, null,
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
        reload = false;
        $etabType.val(null);
        $etab.val(-1);
        $mois.val($('#mois option:eq(0)')[0].value);
        $(location).attr('href','/');
    });

    $etab.select2({
        disabled: $etab.data('disabled')
    });

    $('.js-select2-mutliple').each(function() {
        $(this).select2({
            placeholder: this.dataset.placeholder
        });
    });
})
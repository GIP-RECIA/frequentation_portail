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
                let check = sessionStorage.getItem(currentSwitch.id) == 'true';
                if (check == null) {
                    sessionStorage.setItem(currentSwitch.id, currentSwitch.checked);
                } else {
                    currentSwitch.checked = check;
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
    jQuery.fn.dataTableExt.oSort["percent-asc"]  = function(x,y) {
        const xa = parseFloat(x.split("%")[0]);
        const ya = parseFloat(y.split("%")[0]);
        return ((xa < ya) ? -1 : ((xa > ya) ? 1 : 0));
    };

    jQuery.fn.dataTableExt.oSort["percent-desc"] = function(x,y) {
        return jQuery.fn.dataTableExt.oSort["percent-asc"](y, x);
    };

    const $mois = $('#mois');
    const $etab = $('#etab');
    const $etabType = $('#etabType');
    let reload = true;

    const perType = { "sType": "percent" };

    $('.top20').click (function () {
        const serviceTitle = $(this).parent().contents().get(2).nodeValue.trim();
        $.ajax({
            url: "./top.php",
            type: "POST",
            async: false,
            data: ({
                serviceId: $(this).attr('data-serviceid'),
                mois: $mois.val()
            }),
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
    const reloadFilters = function () {
        if (reload) {
            $.ajax({
                url: "./reloadFilters.php",
                type: "POST",
                async: false,
                data: ({
                    mois: $mois.val(),
                    etabType: $etabType.val()
                }),
                complete: function(data){
                    const actualEtab = $etab.val();
                    const actualType = $etabType.val();
                    $etab.empty().append(new Option("Tous les établissements", "-1", true, actualEtab == -1))
                    $etabType.empty();
                    data.responseJSON['etabs'].forEach((val) => {
                        $etab.append(new Option(val['nom'], val['id'], false, actualEtab == val['id']))
                    });
                    data.responseJSON['types'].forEach((val) => {
                        $etabType.append(new Option(val['nom'], val['id'], false, actualType.includes(val['id'])))
                    });
                }
            });
        }
    };

    $mois.change(reloadFilters);
    $etabType.change(reloadFilters);

    $('#result').DataTable({
        autoWidth: false,
        paging: false,
        ordering: true,
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
                                return data.replace(/( |&nbsp;|<\/?i>)/g, '').replace(/<br>/g,' - ');
                            }
                        }
                    }
                }
            }
        ],
        columnDefs: [
            {
                targets: "_all",
                className: 'dt-body-right'
            }, {
                targets: 0,
                className: 'dt-body-left'
            },
            {
                targets: [1,2,3,4, 5,6,7,8,9,10],
                render: $.fn.dataTable.render.number(' ', '.', 0, '')
            }
        ],
        aoColumns: [
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
        reload = false;
        $etabType.val(null);
        $etab.val(-1);
        $mois.val($('#mois option:eq(0)')[0].value);
        $(location).attr('href','/');
    });

    $etab.select2({
        disabled: $etab.data('disabled')
    });

    // Mutliple select Etablissement
    $('.js-select2-mutliple').select2({
        placeholder: "Tous le types"
    });
})
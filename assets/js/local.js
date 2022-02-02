document.addEventListener('DOMContentLoaded', function () {
    const table = document.getElementById("result");

    document.querySelectorAll('.switch-auto').forEach(
        currentValue => currentValue.addEventListener('change', function() {
            const val1 = this.dataset.val1;
            const val2 = this.dataset.val2;
            if (this.checked) {
                table.classList.replace(val1, val2);
            } else {
                table.classList.replace(val2, val1);
            }
        })
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
                                return data.replace(/<br>/g,' - ').replace(/ /g, '');
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
        $('#etabType').val(null);
        $('#etab').val(-1);
        $('#mois').val(-1);
        $(location).attr('href','/');
    });

    $('#etab').select2({
        disabled: $('#etab').data('disabled')
    });

    // Mutliple select Etablissement
    $('.js-select2-mutliple').select2({
        placeholder: "Tous le types"
    });
})
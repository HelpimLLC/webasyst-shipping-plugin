$(function () {
    $('#helpim_delivery_date').change(function (e) {
        $('input[name="shipping_' + pluginId + '[delivery_date]').val($(this).val());
    });
});

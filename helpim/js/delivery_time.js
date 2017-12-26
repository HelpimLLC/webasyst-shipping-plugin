$(function () {
    $('#helpim_delivery_time_slider').slider({
        range: true,
        min: 6 * 60,
        max: 22 * 60,
        step: 30,
        values: [10 * 60, 18 * 60],
        slide: function (e, ui) {
            if ((ui.values[1] - ui.values[0]) < (3 * 60)) {
                return false;
            }

            let from = fn(ui.values[0] / 60) + ':' + fn(ui.values[0] % 60),
                to = fn(ui.values[1] / 60) + ':' + fn(ui.values[1] % 60);

            $('#helpim_delivery_time_from' ).val(from);
            $('#helpim_delivery_time_to').val(to);
            $('input[name="shipping_' + pluginId + '[delivery_time_from]').val(from);
            $('input[name="shipping_' + pluginId + '[delivery_time_to]').val(to);
        },
    });

    /* format number */
    function fn(n) {
        n = Number.parseInt(n);

        if (n < 10) {
            return '0' + n;
        }

        return n;
    }
});

$(function() {
    let map,
        tariffList = [];

    let deliveryServiceCheckbox = {},
        deliveryTypeCheckbox = {},
        $autocompleteInput = $('#helpim_delivery_tariff_list > input'),
        $deliveryDateDiv = $('label[for="wahtmlcontrol_shipping_' + pluginId + '_delivery_date_control"]').parent().parent().hide(),
        $deliveryTimeDiv = $('label[for="wahtmlcontrol_shipping_' + pluginId + '_delivery_time_control"]').parent().parent().hide(),
        $duration = $('.shipping-' + pluginId + ' > div.rate > span > strong.est_delivery'),
        $point = $('#wahtmlcontrol_shipping_' + pluginId + '_delivery_point'),
        $price = $('.shipping-' + pluginId + ' > div.rate > span.price'),
        $rate = $('select[name="rate_id[' + pluginId + ']"]').hide(),
        $selected = $('#helpim_delivery_selected'),
        $service = $('#wahtmlcontrol_shipping_' + pluginId + '_delivery_service'),
        $shippingRates = $('select.shipping-rates[name="rate_id[' + pluginId + ']"]');

    /* is there a better way to use this empty <p>? */
    $selected.parent().children('p').remove();

    /* ensure hidden fields are completely hidden */
    $('div.wa-field > div.wa-value > input.hidden[type="hidden"]').parent().parent().hide();

    $.getScript('https://api-maps.yandex.ru/2.1/?lang=ru_RU', function () {
        ymaps.ready(getTariffList);
    });

    function findTariffById(id) {
        if (!id) {
            return false;
        }

        for (let i = 0, l = tariffList.length; i < l; i++) {
            if (tariffList[i].id == id) {
                return tariffList[i];
            }
        }

        return false;
    }

    function getTariffList() {
        map = new ymaps.Map('helpim_delivery_map', {
            center: [65, 100],
            zoom: 2,
        });

        $.getJSON(getTariffListUrl).done(function (data) {
                tariffList = data;

                /* restore tariff if was selected */
                setTariff(findTariffById($shippingRates.val()));

                makeAutocomplete();
                placeMarks();
        });
    }

    function placeMarks() {
        let balloonContentLayout = ymaps.templateLayoutFactory.createClass(
            '<div>' +
                '<div class="bold">{{properties.tariff.name}} {{properties.tariff.point}}</div>' +
                '<div>' +
                    '{{properties.tariff.duration}}, <span class="price nowrap">{{properties.tariff.cost}} {{properties.currency}}</span>' +
                    '<br>' +
                    '{{properties.tariff.address}}' +
                '</div>' +
                '<div class="ymaps-2-1-56-balloon-content__footer"><a href="#" class="helpim-point-mark-choose" data-index="{{properties.tariffIndex}}">Выбрать</a></div>' +
            '</div>', {
            build: function () {
                balloonContentLayout.superclass.build.call(this);
                $('a.helpim-point-mark-choose').off().click(function (e) {
                    e.preventDefault();
                    setTariff(tariffList[$(this).data('index')]);
                });
            },
            clear: function () {
                balloonContentLayout.superclass.clear.call(this);
                $('a.helpim-point-choose').off();
            },
        });

        let clusterer = new ymaps.Clusterer({
            clusterBalloonItemContentLayout: balloonContentLayout,
            clusterDisableClickZoom: true,
            groupByCoordinates: true,
        });

        tariffList.forEach(function (tariff, index) {
            if (tariff.type != 'selfDelivery') {
                return;
            }

            clusterer.add(new ymaps.Placemark([tariff.latitude, tariff.longitude], {
                hintContent: tariff.name + ', ' + tariff.cost + ' ' + currency,
                balloonContentHeader: tariff.name,
                currency: currency,
                tariff: tariff,
                tariffIndex: index,
            }, {
                balloonContentLayout: balloonContentLayout,
            }));
        });

        map.geoObjects.add(clusterer);
        map.setBounds(clusterer.getBounds());

        $('input[type="radio"][name="shipping_id"]').change(function (e) {
            if ($(this).val() != pluginId) {
                return;
            }

            map.setBounds(clusterer.getBounds());
        });
    }

    function setTariff(tariff) {
        if (!tariff) {
            return;
        }

        if (tariff.type == 'courier') {
            $deliveryDateDiv.show();
            $deliveryTimeDiv.show();
        } else {
            $deliveryDateDiv.hide();
            $deliveryTimeDiv.hide();
        }

        $selected.html(deliveryType[tariff.type] + ' ' + tariff.name + '<br>' + tariff.desc);
        $price.html(tariff.cost.toLocaleString() + ' ' + currency);
        if (tariff.duration !== null) {
            $duration.html(tariff.duration).parent().show();
        } else {
            $duration.html('').parent().hide();
        }
        $rate.val(tariff.id);
        $service.val(tariff.code);
        if (tariff.point !== null) {
            $point.val(tariff.point);
        } else {
            $point.val(null);
        }
    }

    function sortTariffList(a, b, field) {
        if (field === undefined) {
            field = 'cost';
        }

        if (b[field] === undefined || a[field] < b[field]) {
            return -1;
        }
        if (a[field] === undefined || a[field] > b[field]) {
            return 1;
        }
        return 0;
    }

    function sortTariffListByCostAsc(a, b) {
         return a.cost - b.cost;
    }

    $('#helpim_delivery_type_groupbox > label > input[type="checkbox"]').each(function () {
        let $this = $(this);
        deliveryTypeCheckbox[$this.attr('name')] = $this;
    });
    $('#helpim_delivery_service_groupbox > label > input[type="checkbox"]').each(function () {
        let $this = $(this);
        deliveryServiceCheckbox[$this.attr('name')] = $this;
    });

    function makeAutocomplete() {
        $autocompleteInput
            .autocomplete({
                minLength: 0,
                source: tariffList,
                focus: function (e, ui) {
                    return false;
                },
                select: function (e, ui) {
                    setTariff(ui.item);
                    return false;
                },
            })
            .autocomplete('instance')._renderItem = function(ul, tariff) {
                if (!deliveryTypeCheckbox[tariff.type].prop('checked')) {
                    return $('<li>').hide();
                }
                if (!deliveryServiceCheckbox[tariff.service].prop('checked')) {
                    return $('<li>').hide();
                }

                return $('<li>')
                    .append('<div><div class="helpim-shipping-name">' + deliveryType[tariff.type] + ' ' + tariff.name +
                        (tariff.duration ? ', ' + tariff.duration : '') + ', <span class="price nowrap">' + tariff.cost + ' ' + currency +
                        '</span></div><div class="hint">' + tariff.desc + '</div></div>')
                    .appendTo(ul);
            };
        $autocompleteInput
            .click(function () {
                $(this).autocomplete('search', $autocompleteInput.val());
            })
            .focus(function () {
                $(this).autocomplete('search', $autocompleteInput.val());
            });
    }
});

<?php

return array(
    'customer_service_id' => array(
        'title'        => 'Идентификатор подключения',
        'control_type' => waHtmlControl::INPUT,
        'description'  => 'Получите в <a href="https://cabinet.help-im.ru" target="_black">Личном кабинете Helpim</a>',
    ),
    'token' => array(
        'title'        => 'API-токен',
        'control_type' => waHtmlControl::INPUT,
        'description'  => 'Получите в <a href="https://cabinet.help-im.ru" target="_black">Личном кабинете Helpim</a>',
    ),
    'add_days' => array(
        'title'        => 'Прибавить дней',
        'control_type' => waHtmlControl::INPUT,
        'description'  => 'Количество дней, которое следует прибавить к рассчитанной длительности доставки (например, требующихся на подготовку заказа к отправке)',
        'value'        => 0,
    ),
    'default_weight' => array(
        'title'        => 'Вес заказа по умолчанию, г',
        'control_type' => waHtmlControl::INPUT,
        'description'  => 'Если не удалось рассчитать вес заказа, считать его равным указанному значению. ВНИМАНИЕ! Крайне не рекомендуется рассчитывать на "вес по умолчанию", т.к. вес влияет на стоимость доставки, лучшим решением будет указать его для каждого товара',
        'value'        => 0,
    ),
    'courier_cost' => array(
        'title'        => 'Стоимость доставки курьером по умолчанию',
        'control_type' => waHtmlControl::INPUT,
        'description'  => 'Для отключения поле следует оставить пустым, тогда вырианты доставки, для которых не получен рассчёт цены, будут скрыты',
    ),
    'courier_cost_using' => array(
        'title'        => 'Использовать стоимость доставки курьером по умолчанию',
        'control_type' => waHtmlControl::RADIOGROUP,
        'options'      => array(
            array(
                'value' => 0,
                'title' => 'Всегда',
            ),
            array(
                'value' => 1,
                'title' => 'Когда не удалось получить рассчёт стоимости от курьерской компании',
            ),
        ),
    ),
    'self_delivery_cost' => array(
        'title'        => 'Стоимость самовывоза из ПВЗ по умолчанию',
        'control_type' => waHtmlControl::INPUT,
        'description'  => 'Для отключения поле следует оставить пустым, тогда вырианты доставки, для которых не получен рассчёт цены, будут скрыты',
    ),
    'self_delivery_cost_using' => array(
        'title'        => 'Использовать стоимость самовывоза из ПВЗ по умолчанию',
        'control_type' => waHtmlControl::RADIOGROUP,
        'options'      => array(
            array(
                'value' => 0,
                'title' => 'Всегда',
            ),
            array(
                'value' => 1,
                'title' => 'Когда не удалось получить рассчёт стоимости от курьерской компании',
            ),
        ),
    ),
    'add_cost' => array(
        'title'        => 'Дополнительная наценка к стоимости доставки',
        'control_type' => waHtmlControl::INPUT,
        'description'  => 'Может быть отрицательной (итоговая стоимость доставки не будет меньше нуля)',
        'value'        => 0,
    ),
    'cost_coefficient' => array(
        'title'        => 'Дополнительный % к стоимости доставки',
        'control_type' => waHtmlControl::INPUT,
        'description'  => '% от стоимости доставки',
        'value'        => 0,
    ),
    'shipment_address_zip' => array(
        'title'        => 'Почтовый индекс адреса отгрузки',
        'control_type' => waHtmlControl::INPUT,
        'description'  => 'Некоторые службы доставки требуют указывать адрес забора заказа. Если такие используются, необходимые поля адреса отгрузки следует заполнить'
    ),
    'shipment_address_region' => array(
        'title'        => 'Регион отгрузки',
        'control_type' => waHtmlControl::INPUT,
    ),
    'shipment_address_city' => array(
        'title'        => 'Город отгрузки',
        'control_type' => waHtmlControl::INPUT,
    ),
    'shipment_address_text' => array(
        'title'        => 'Адрес отгрузки',
        'control_type' => waHtmlControl::INPUT,
        'description'  => 'Улица, дом и т.д.',
    ),
);

<?php
/**
 * PHP version 5.2
 *
 * Helpim shipping plugin
 *
 * @category helpim
 * @package  api-client-php
 * @author   Helpim <it@help-im.ru>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     https://help-im.ru
 */

class helpimShipping extends waShipping
{
    const CACHE_NAME = 'helpim_tariff_list';

    private $courierList = array();
    private $pointList = array();
    private $serviceList = array();

    private static $deliveryType = array(
        'courier' => 'Курьер',
        'selfDelivery' => 'Самовывоз',
    );

    private function addCost($cost)
    {
        $newCost = (int) $cost;

        if (isset($this->add_cost))  {
            $newCost += (int) $this->add_cost;
        }

        if (isset($this->cost_coefficient) && $this->cost_coefficient > 0) {
            $newCost += (int) $cost * (int) $this->cost_coefficient / 100;
        }

        if ($newCost <= 0) {
            return 0;
        }

        return $newCost;
    }

    public function allowedAddress()
    {
        return array(
            array(
                'country' => 'rus',
            ),
        );
    }

    public function allowedCurrency()
    {
        return 'RUB';
    }

    public function allowedWeightUnit()
    {
        return 'g';
    }

    private function cacheResult(array $result)
    {
        $cache = array();

        foreach ($result as $service) {
            if (empty($this->serviceList[$service['serviceCode']])) {
                $this->serviceList[$service['serviceCode']] = $service['name'];
            }

            /* длительность доставки */
            $duration = null;
            $addDays = (int) $this->add_days;
            if (isset($service['minTerm']) || isset($service['maxTerm'])) {
                if ($service['minTerm'] == $service['maxTerm']) {
                    $duration = $service['minTerm'] + $addDays;
                } elseif (!isset($service['minTerm'])) {
                    $duration = $service['maxTerm'] + $addDays;
                } else if (!isset($service['maxTerm'])) {
                    $duration = $service['minTerm'] + $addDays;
                } else {
                    $duration = ($service['minTerm'] + $addDays) . ' - ' . ($service['maxTerm'] + $addDays);
                }

                $duration .= ' дн.';
            }

            /* список доставок курьером */
            if ($service['type'] == 'courier') {
                $serviceCost = isset($service['cost']) ? $service['cost'] : null;
                /* задана цена по умолчанию */
                if (isset($this->courier_cost) && $this->courier_cost !== "") {
                    /* всегда использовать цену по умолчанию */
                    if ($this->courier_cost_using == "0") {
                        $serviceCost = (int) $this->courier_cost;
                    /* только если не получена цена от КК */
                    } elseif ($this->courier_cost_using == "1" && !isset($service['cost'])) {
                        $serviceCost = (int) $this->courier_cost;
                    }
                }

                if (!isset($serviceCost)) {
                    continue;
                }

                $this->courierList[] = array(
                    'id' => $service['code'],
                    'value' => $service['name'],
                    'code' => $service['code'],
                    'service' => $service['serviceCode'],
                    'point' => null,
                    'name' => $service['name'],
                    'type' => $service['type'],
                    'cost' => $this->addCost($serviceCost),
                    'desc' => $service['description'],
                    'duration' => $duration,
                );

                continue;
            }

            if (empty($service['pickuppointList'])) {
                continue;
            }

            $serviceCost = isset($service['cost']) ? $service['cost'] : null;
            /* задана цена по умолчанию */
            if (isset($this->self_delivery_cost) && $this->self_delivery_cost !== "") {
                /* всегда использовать цену по умолчанию */
                if ($this->self_delivery_cost_using == "0") {
                    $serviceCost = (int) $this->self_delivery_cost;
                /* только если не получена цена от КК */
                } elseif ($this->self_delivery_cost_using == "1" && !isset($service['cost'])) {
                    $serviceCost = (int) $this->self_delivery_cost;
                }
            }

            /* список ПВЗ */
            foreach ($service['pickuppointList'] as $point) {
                /* цена по умолчанию */
                $pointCost = isset($point['cost']) ? $point['cost'] : $serviceCost;
                if (isset($this->self_delivery_cost) && $this->self_delivery_cost !== "") {
                    /* всегда использовать цену по умолчанию */
                    if ($this->self_delivery_cost_using == "0") {
                        $pointCost = (int) $this->self_delivery_cost;
                    /* только если не получена цена от КК */
                    } elseif ($this->self_delivery_cost_using == "1" && !isset($pointCost)) {
                        $pointCost = (int) $this->self_delivery_cost;
                    }
                }

                if (!isset($pointCost)) {
                    continue;
                }

                $this->pointList[] = array(
                    'id' => $service['code'] . '.' . $point['code'],
                    'value' => $point['address'],
                    'code' => $service['code'],
                    'service' => $service['serviceCode'],
                    'point' => $point['code'],
                    'name' => $service['name'],
                    'type' => $service['type'],
                    'cost' => $this->addCost($pointCost),
                    'desc' => $point['address'],
                    'duration' => $duration,
                    'address' => $point['address'],
                    'latitude' => (float) $point['latitude'],
                    'longitude' => (float) $point['longitude'],
                );
            }
        }

        asort($this->serviceList);

        $cache = array_merge($this->sortTariffListByCostAsc($this->courierList),
            $this->sortTariffListByCostAsc($this->pointList));

        wa()->getStorage()->set(self::CACHE_NAME, $cache);

        return $cache;
    }

    public function calculate()
    {
        if (!$this->request()) {
            return 'Не найдено ни одного подходящего варианта доставки. Проверьте адрес';
        }

        $rates = array(
            '0' => array(
                'name' => '-- выберите способ доставки --',
                'rate' => null,
                'currency' => $this->currency,
            ),
        );

        $currency = waCurrency::getInfo(wa()->getSetting('currency'));

        foreach ($this->getCachedResult() as $tariff) {
            $address = '';
            $point = '';
            if ($tariff['type'] == 'selfDelivery') {
                if (!empty($tariff['address'])) {
                    $address = ', ' . $tariff['address'];
                }
                $point = ' ' . $tariff['point'];
            }

            $duration = '';
            if (isset($tariff['duration'])) {
                $duration = ', ' . $tariff['duration'];
            }

            $rates[$tariff['id']] = array(
                'name' => sprintf('%s %s%s, %.2f %s%s%s', self::$deliveryType[$tariff['type']], $tariff['name'],
                    @$point, $tariff['cost'], $currency['sign'], $duration, $address),
                'description' => @$tariff['address'],
                'est_delivery' => $tariff['duration'],
                'rate' => $tariff['cost'],
                'currency' => $this->currency,
            );
        }

        return $rates;
    }

    public function customFields(waOrder $order)
    {
        $shipping_params = $order->shipping_params;
        $fields = parent::customFields($order);

        $this->registerControl('HelpimSelectTrariffControl', array($this, 'customSelectTrariffControl'));
        $fields['delivery_tariff_control'] = array(
            'title'        => 'Вариант доставки',
            'control_type' => 'HelpimSelectTrariffControl',
            'data'         => array(
                'affects-rate' => true,
            ),
        );

        $fields['delivery_date'] = array(
            'title'        => 'Желаемая дата доставки',
            'control_type' => waHtmlControl::HIDDEN,
        );

        $this->registerControl('HelpimDeliveryDateControl', array($this, 'customDeliveryDateControl'));
        $fields['delivery_date_control'] = array(
            'title'        => 'Желаемая дата доставки',
            'control_type' => 'HelpimDeliveryDateControl',
            'description'  => 'Только для доставки курьером'
        );

        $fields['delivery_time_from'] = array(
            'title'        => 'Желаемое время доставки, с',
            'control_type' => waHtmlControl::HIDDEN,
        );

        $fields['delivery_time_to'] = array(
            'title'        => 'Желаемое время доставки, до',
            'control_type' => waHtmlControl::HIDDEN,
        );

        $this->registerControl('HelpimDeliveryTimeControl', array($this, 'customDeliveryTimeControl'));
        $fields['delivery_time_control'] = array(
            'title'        => 'Желаемое время доставки',
            'control_type' => 'HelpimDeliveryTimeControl',
            'description'  => 'Только для доставки курьером. Интервал не менее 3-х часов'
        );

        return $fields;
    }

    public function customDeliveryDateControl()
    {
        $dom = new DOMDocument();
        $now = new DateTime();

        $input = $dom->createElement('input');
        $input->setAttribute('type', 'date');
        $input->setAttribute('id', 'helpim_delivery_date');
        $input->setAttribute('min', $now->format('Y-m-d'));
        $dom->appendChild($input);

        $script = $dom->createElement('script');
        $script->setAttribute('src', wa()->getUrl() . 'wa-plugins/shipping/helpim/js/delivery_date.js');
        $dom->appendChild($script);

        return $dom->saveHTML();
    }

    public function customDeliveryTimeControl()
    {
        $dom = new DOMDocument();

        $div = $dom->createElement('div');
        $div->setAttribute('id', 'helpim_delivery_time_slider');
        $dom->appendChild($div);

        $input = $dom->createElement('input');
        $input->setAttribute('type', 'time');
        $input->setAttribute('id', 'helpim_delivery_time_from');
        $input->setAttribute('readonly', true);
        $input->setAttribute('value', '10:00');
        $dom->appendChild($input);

        $dom->appendChild($dom->createTextNode(' - '));

        $input = $dom->createElement('input');
        $input->setAttribute('type', 'time');
        $input->setAttribute('id', 'helpim_delivery_time_to');
        $input->setAttribute('readonly', true);
        $input->setAttribute('value', '18:00');
        $dom->appendChild($input);

        $script = $dom->createElement('script');
        $script->setAttribute('src', wa()->getUrl() . 'wa-plugins/shipping/helpim/js/delivery_time.js');
        $dom->appendChild($script);

        return $dom->saveHTML();
    }

    public function customSelectTrariffControl()
    {
        $url_params = array(
            'action_id' => 'getTariffList',
            'plugin_id' => $this->key,
        );
        $url = wa()->getRouteUrl($this->app_id . '/frontend/shippingPlugin', $url_params, true);

        $currency = waCurrency::getInfo(wa()->getSetting('currency'));

        $dom = new DOMDocument();

        $div = $dom->createElement('div');
        $div->appendChild($dom->createTextNode('Введите несколько символов'));
        $div->appendChild($dom->createElement('br'));
        $div->appendChild($dom->createTextNode('в поле выбора доставки:'));
        $div->setAttribute('class', 'bold');
        $div->setAttribute('id', 'helpim_delivery_selected');
        $dom->appendChild($div);

        /* Delivery type checkbox group */
        $div = $dom->createElement('div');
        $div->setAttribute('id', 'helpim_delivery_type_groupbox');
        $div->appendChild($dom->createTextNode('Способ доставки'));
        foreach (self::$deliveryType as $type => $name) {
            $label = $dom->createElement('label');
            $checkbox = $dom->createElement('input');
            $checkbox->setAttribute('type', 'checkbox');
            $checkbox->setAttribute('name', $type);
            $checkbox->setAttribute('checked', true);
            $label->appendChild($checkbox);
            $label->appendChild($dom->createTextNode($name));
            $div->appendChild($label);
        }
        $dom->appendChild($div);

        /* Delivery service checkbox group */
        $div = $dom->createElement('div');
            $div->setAttribute('id', 'helpim_delivery_service_groupbox');
        $div->appendChild($dom->createTextNode('Сервис доставки'));
        foreach ($this->serviceList as $service => $name) {
            $label = $dom->createElement('label');
            $checkbox = $dom->createElement('input');
            $checkbox->setAttribute('type', 'checkbox');
            $checkbox->setAttribute('name', $service);
            $checkbox->setAttribute('checked', true);
            $label->appendChild($checkbox);
            $label->appendChild($dom->createTextNode($name));
            $div->appendChild($label);
        }
        $dom->appendChild($div);

        /* Delivery tariff list */
        $div = $dom->createElement('div');
        $div->setAttribute('id', 'helpim_delivery_tariff_list');
        $input = $dom->createElement('input');
        $input->setAttribute('class', 'long');
        $input->setAttribute('placeholder', 'Выбор способа доставки');
        $div->appendChild($input);
        $dom->appendChild($div);

        /* Map */
        $div = $dom->createElement('div', 'ПВЗ на карте:');
        $dom->appendChild($div);
        $div = $dom->createElement('div');
        $div->setAttribute('id', 'helpim_delivery_map');
        $dom->appendChild($div);

        $script = $dom->createElement('script', "
            /* Decode entity */
            function d(str) {
                return $('<p>' + str + '</p>').text();
            }

            var getTariffListUrl = '{$url}',
            pluginId = {$this->key},
            currency = d('" . $currency['sign'] . "'),
            deliveryType = {
                courier: d('Курьер'),
                selfDelivery: d('Самовывоз'),
            };");
        $dom->appendChild($script);

        $link = $dom->createElement('link');
        $link->setAttribute('rel', 'stylesheet');
        $link->setAttribute('type', 'text/css');
        $link->setAttribute('href', wa()->getUrl() . 'wa-plugins/shipping/helpim/css/jquery-ui.theme.min.css');
        $dom->appendChild($link);

        $link = $dom->createElement('link');
        $link->setAttribute('rel', 'stylesheet');
        $link->setAttribute('type', 'text/css');
        $link->setAttribute('href', wa()->getUrl() . 'wa-plugins/shipping/helpim/css/jquery-ui.min.css');
        $dom->appendChild($link);

        $link = $dom->createElement('link');
        $link->setAttribute('rel', 'stylesheet');
        $link->setAttribute('type', 'text/css');
        $link->setAttribute('href', wa()->getUrl() . 'wa-plugins/shipping/helpim/css/style.css');
        $dom->appendChild($link);

        $script = $dom->createElement('script');
        $script->setAttribute('src', wa()->getUrl() . 'wa-plugins/shipping/helpim/js/jquery-ui.min.js');
        $dom->appendChild($script);

        $script = $dom->createElement('script');
        $script->setAttribute('src', wa()->getUrl() . 'wa-plugins/shipping/helpim/js/script.js');
        $dom->appendChild($script);

        return $dom->saveHTML();
    }

    private function getCachedResult()
    {
        return wa()->getStorage()->get(self::CACHE_NAME);
    }

    public function getTariffListAction()
    {
        echo json_encode($this->getCachedResult());
    }

    protected function init()
    {
        $autoload = waAutoload::getInstance();
        $suffix = '.class.php';
        $suffix_len = strlen($suffix);

        foreach (waFiles::listdir('./wa-plugins/shipping/helpim/lib/classes/') as $file) {
            if (substr($file, strlen($file) - $suffix_len) != $suffix) {
                continue;
            }

            $autoload->add(
                substr($file, 0, -$suffix_len),
                "wa-plugins/shipping/helpim/lib/classes/$file"
            );
        }

        parent::init();
    }

    private function request()
    {
        try {
            $helpim = new helpimShippingProxy($this->customer_service_id, $this->token);
            $param = array();

            if ($this->shipment_address_zip ||
                $this->shipment_address_region ||
                $this->shipment_address_city ||
                $this->shipment_address_text
            ) {
                $param['shipmentAddress'] = array(
                    'countryIso' => 'RU',
                    'index' => $this->shipment_address_zip,
                    'region' => $this->shipment_address_region,
                    'city' => $this->shipment_address_city,
                    'text' => $this->shipment_address_text,
                );
            }

            $waRegionModel = new waRegionModel();
            $region = $waRegionModel->get('rus', $this->getAddress('region'));

            $param['deliveryAddress'] = array(
                'countryIso' => 'RU',
                'index' => $this->getAddress('zip'),
                'region' => $region['name'],
                'city' => $this->getAddress('city'),
                'text' => $this->getAddress('street'),
            );

            $param['packages'][] = array(
                'weight' => $this->getTotalWeight() ? $this->getTotalWeight() : (int) $this->default_weight,
            );

            $param['declaredValue'] = $this->getTotalPrice();

            $responce = $helpim->calculate($param);

            return $this->cacheResult($responce->getResult());
        } catch (Exception $e) {
            helpimShippingHelper::log($e->getMessage());
            return false;
        }
    }

    public function requestedAddressFields()
    {
        return array(
            'country' => array('cost' => true),
            'region'  => array('cost' => true),
            'city'    => array('cost' => true),
        );
    }

    private function sortByCost($a, $b)
    {
        return $a['cost'] - $b['cost'];
    }

    private function sortTariffListByCostAsc(array $tariffList)
    {
        usort($tariffList, array($this, 'sortByCost'));
        return $tariffList;
    }
}

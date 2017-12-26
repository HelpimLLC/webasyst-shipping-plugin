<?php
/**
 * PHP version 5.2
 *
 * Wrapper
 *
 * @category helpim
 * @package  api-client-php
 * @author   Helpim <it@help-im.ru>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     https://help-im.ru
 */

class helpimShippingProxy
{
    private $api;

    public function __construct($customerServiceId, $token)
    {
        $customerServiceId = (int) $customerServiceId;

        if (!$customerServiceId || !$token) {
            $error = 'Missed customerServiceId or/and token';
            helpimHelper::log('[proxy_init] ' . $error);
            throw new DomainException($error);
        }

        $this->api = new helpimShippingHttpClient(array(
            'customerServiceId' => $customerServiceId,
            'token' => $token,
        ));
    }

    /**
     * Make simple request to Helpim API
     *
     * The method name is used as a name of structure which should be sent.
     *
     * @param string $method    Method name
     * @param array  $arguments Method arguments
     *
     * @return helpimShippingClientResponse
     */
    public function __call($method, array $arguments)
    {
        $response = $this->_request(array($method => $arguments[0]));

        if (!$response->isSuccessful()) {
            helpimShippingHelper::log(sprintf('[%s] %s (%s)', $method, $response->getError(), $response->getMessage()));
            throw new Exception($response->getError() . ': ' . $response->getMessage(), $response->getStatusCode());
        }

        return $response;
    }

    /**
     * Make request to Helpim API
     *
     * @param array $data An array of data
     *
     * @return helpimShippingClientResponse
     */
    private function _request(array $data)
    {
        $retry = 3;
        $pause = 1;

        while ($retry--) {
            try {
                return $this->api->request($data);
            } catch (helpimShippingCurlException $e) {
                helpimShippingHelper::log('cURL error: ' . $e->getMessage() . ($retry > 0 ? '. retries left: ' . $retry : ''));
            } catch (helpimShippingInvalidJsonException $e) {
                helpimShippingHelper::log('JSON error: ' . $e->getMessage() . ' in "' . $e->getSourceString() . '"');
                break;
            }

            sleep($pause);
        }

        throw new Exception('Error: Can not complete request to Helpim');
    }

    /**
     * Make custom request to Helpim API
     *
     * @param array $data An array of data
     *
     * @return helpimShippingClientResponse
     */
    public function request(array $data)
    {
        $response = $this->_request($data);

        if (!$response->isSuccessful()) {
            helpimShippingHelper::log(sprintf('[_custom_] %s (%s)', $response->getError(), $response->getMessage()));
            throw new Exception($response->getError() . ': ' . $response->getMessage(), $response->getStatusCode());
        }

        return $response;
    }
}

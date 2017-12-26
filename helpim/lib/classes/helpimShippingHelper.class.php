<?php
/**
 * PHP version 5.2
 *
 * Helpim helper set
 *
 * @category helpim
 * @package  api-client-php
 * @author   Helpim <it@help-im.ru>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     https://help-im.ru
 */

class helpimShippingHelper
{
    const LOG_FILE = 'plugins/shipping/helpim.log';

    public static function log($message) {
        waLog::log($message, self::LOG_FILE);
    }
}

<?php
/**
 * KohaRest ILS Driver
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2017.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
namespace Finna\ILS\Driver;

use VuFind\Exception\ILS as ILSException;

/**
 * VuFind Driver for Koha, using REST API
 *
 * Minimum Koha Version: work in progress as of 23 Jan 2017
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class KohaRest extends \VuFind\ILS\Driver\KohaRest
{
    /**
     * Get Patron Fines
     *
     * This is responsible for retrieving all fines by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws DateException
     * @throws ILSException
     * @return array        Array of the patron's fines on success.
     */
    public function getMyFines($patron)
    {
        $fines = parent::getMyFines($patron);
        foreach ($fines as &$fine) {
            $fine['payableOnline'] = true;
        }
        return $fines;
    }

    /**
     * Return total amount of fees that may be paid online.
     *
     * @param array $patron Patron
     *
     * @throws ILSException
     * @return array Associative array of payment info,
     * false if an ILSException occurred.
     */
    public function getOnlinePayableAmount($patron)
    {
        $fines = $this->getMyFines($patron);
        if (!empty($fines)) {
            $amount = 0;
            foreach ($fines as $fine) {
                $amount += $fine['balance'];
            }
            $config = $this->getConfig('onlinePayment');
            $nonPayableReason = false;
            if (isset($config['minimumFee']) && $amount < $config['minimumFee']) {
                $nonPayableReason = 'online_payment_minimum_fee';
            }
            $res = ['payable' => empty($nonPayableReason), 'amount' => $amount];
            if ($nonPayableReason) {
                $res['reason'] = $nonPayableReason;
            }
            return $res;
        }
        return [
            'payable' => false,
            'amount' => 0,
            'reason' => 'online_payment_minimum_fee'
        ];
    }

    /**
     * Mark fees as paid.
     *
     * This is called after a successful online payment.
     *
     * @param array  $patron        Patron.
     * @param int    $amount        Amount to be registered as paid
     * @param string $transactionId Transaction ID
     *
     * @throws ILSException
     * @return boolean success
     */
    public function markFeesAsPaid($patron, $amount, $transactionId)
    {
        $request = [
            'amount' => $amount / 100,
            'note' => "Online transaction $transactionId"
        ];
        $operator = $patron;
        if (!empty($this->config['onlinePayment']['userId'])
            && !empty($this->config['onlinePayment']['userPassword'])
        ) {
            $operator = [
                'cat_username' => $this->config['onlinePayment']['userId'],
                'cat_password' => $this->config['onlinePayment']['userPassword']
            ];
        }

        list($code, $result) = $this->makeRequest(
            ['v1', 'patrons', $patron['id'], 'payment'],
            json_encode($request),
            'POST',
            $operator,
            true
        );
        if ($code != 204) {
            $error = "Failed to mark payment of $amount paid for patron"
                . " {$patron['id']}: $code: $result";

            $this->error($error);
            throw new ILSException($error);
        }
        return true;
    }
}

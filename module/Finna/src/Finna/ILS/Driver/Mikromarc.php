<?php
/**
 * Mikromarc ILS Driver
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Bjarne Beckmann <bjarne.beckmann@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
namespace Finna\ILS\Driver;
use VuFind\Exception\Date as DateException;
use VuFind\Exception\ILS as ILSException;

/**
 * Mikromarc ILS Driver
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Bjarne Beckmann <bjarne.beckmann@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class Mikromarc extends \VuFind\ILS\Driver\AbstractBase implements
    \VuFindHttp\HttpServiceAwareInterface,
    \VuFind\I18n\Translator\TranslatorAwareInterface, \Zend\Log\LoggerAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use \VuFind\Log\LoggerAwareTrait {
        logError as error;
    }

    /**
     * Date converter object
     *
     * @var \VuFind\Date\Converter
     */
    protected $dateConverter;

    protected $translator;

    /**
     * Institution settings for the order of organisations
     *
     * @var string
     */
    protected $holdingsOrganisationOrder;

    /**
     * Default pickup location
     *
     * @var string
     */
    protected $defaultPickUpLocation;

     /**
     * Mappings from fee (account line) types
     *
     * @var array
     */
    protected $feeTypeMappings = [
        'Overdue charge' => 'Overdue',
        'Extra service' => 'Extra service'
    ];

    /**
     * Constructor
     *
     * @param \VuFind\Date\Converter $dateConverter Date converter object
     */
    public function __construct(\VuFind\Date\Converter $dateConverter, $translator
    ) {
        $this->dateConverter = $dateConverter;
        $this->translator = $translator;
    }

    /**
     * Initialize the driver.
     *
     * Validate configuration and perform all resource-intensive tasks needed to
     * make the driver active.
     *
     * @throws ILSException
     * @return void
     */
    public function init()
    {
        $this->holdingsOrganisationOrder
            = isset($this->config['Holdings']['holdingsOrganisationOrder'])
            ? explode(':', $this->config['Holdings']['holdingsOrganisationOrder'])
            : [];

        $this->defaultPickUpLocation
            = isset($this->config['Holds']['defaultPickUpLocation'])
            ? $this->config['Holds']['defaultPickUpLocation']
            : '';
        if ($this->defaultPickUpLocation === 'user-selected') {
            $this->defaultPickUpLocation = false;
        }
    }

    /**
     * Public Function which retrieves renew, hold and cancel settings from the
     * driver ini file.
     *
     * @param string $function The name of the feature to be checked
     * @param array  $params   Optional feature-specific parameters (array)
     *
     * @return array An array with key-value pairs.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getConfig($function, $params = null)
    {
        return isset($this->config[$function])
            ? $this->config[$function] : false;
    }

    /**
     * Get Holding
     *
     * This is responsible for retrieving the holding information of a certain
     * record.
     *
     * @param string $id     The record id to retrieve the holdings for
     * @param array  $patron Patron data
     *
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber, duedate,
     * number, barcode.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getHolding($id, array $patron = null)
    {
        return $this->getItemStatusesForBiblio($id, $patron);
    }

    /**
     * Get Purchase History
     *
     * This is responsible for retrieving the acquisitions history data for the
     * specific record (usually recently received issues of a serial).
     *
     * @param string $id The record id to retrieve the info for
     *
     * @return mixed     An array with the acquisitions data on success.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getPurchaseHistory($id)
    {
        return [];
    }

    /**
     * Get Status
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * @param string $id The record id to retrieve the holdings for
     *
     * @return array An associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber.
     */
    public function getStatus($id)
    {
        return $this->getItemStatusesForBiblio($id);
    }

    /**
     * Get Statuses
     *
     * This is responsible for retrieving the status information for a
     * collection of records.
     *
     * @param array $ids The array of record ids to retrieve the status for
     *
     * @return mixed     An array of getStatus() return values on success.
     */
    public function getStatuses($ids)
    {
        $items = [];
        foreach ($ids as $id) {
            $items[] = $this->getItemStatusesForBiblio($id);
        }
        return $items;
    }

    /**
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $username The patron username
     * @param string $password The patron password
     *
     * @return mixed           Associative array of patron info on successful login,
     * null on unsuccessful login.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function patronLogin($username, $password)
    {
        $request = json_encode(
            [
              'Barcode' => $username,
              'Pin' => $password
            ]
        );

        list($code, $result) = $this->makeRequest(
            ['odata', 'Borrowers', 'Default.Authenticate'],
            $request, 'POST', true
        );

        if ($code != 200 || empty($result)) {
            return null;
        }

        $patron = [
            'cat_username' => $username, 'cat_password' => $password, 'id' => $result
        ];
        
        if ($profile = $this->getMyProfile($patron)) {
            $profile['major'] = null;
            $profile['college'] = null;
        }
        
        return $profile;
    }

        /**
     * Check whether the patron is blocked from placing requests (holds/ILL/SRR).
     *
     * @param array $patron Patron data from patronLogin().
     *
     * @return mixed A boolean false if no blocks are in place and an array
     * of block reasons if blocks are in place
     */
    public function getRequestBlocks($patron)
    {
        return $this->getPatronBlocks($patron);
    }

    /**
     * Check whether the patron has any blocks on their account.
     *
     * @param array $patron Patron data from patronLogin().
     *
     * @return mixed A boolean false if no blocks are in place and an array
     * of block reasons if blocks are in place
     */
    public function getAccountBlocks($patron)
    {
        return $this->getPatronBlocks($patron);
    }

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
        $result = $this->makeRequest(
            ['BorrowerDebts', $patron['cat_username'], '1', '0']
        );

        if (empty($result)) {
            return [];
        }
        $fines = [];
        foreach ($result as $entry) {
            $createDate = !empty($entry['DeptDate'])
                ? $this->dateConverter->convertToDisplayDate(
                    'U', strtotime($entry['DeptDate'])
                )
                : '';

            $type = $entry['Notes'];
            if (isset($this->feeTypeMappings[$type])) {
                $type = $this->feeTypeMappings[$type];
            }
            $amount = $entry['Remainder']*100;
            $fine = [
                'amount' => $amount,
                'balance' => $amount,
                'fine' => $type,
                'createdate' => $createDate,
                'checkout' => '',
                'id' => isset($entry['MarcRecordId'])
                   ? $entry['MarcRecordId'] : null,
                'item_id' => $entry['ItemId']
            ];
            if (!empty($entry['MarcRecordTitle'])) {
                $fine['title'] = $entry['MarcRecordTitle'];
            }
            $fines[] = $fine;

        }
        return $fines;
    }

     /**
     * Get Patron Profile
     *
     * This is responsible for retrieving the profile for a specific patron.
     *
     * @param array $patron The patron array
     *
     * @throws ILSException
     * @return array        Array of the patron's profile data on success.
     */
    public function getMyProfile($patron)
    {
        $cacheKey = $this->getProfileCacheKey($patron);
        if ($profile = $this->getCachedData($cacheKey)) {
            return $profile;
        }
        
        list($code, $result) = $this->makeRequest(
            ['odata', 'Borrowers(' . $patron['id'] . ')'], false, 'GET', true
        );

        if ($code != 200) {
            return null;
        }

        $expirationDate = !empty($result['Expires'])
            ? $this->dateConverter->convertToDisplayDate(
                'Y-m-d', $result['Expires']
            ) : '';

        $name = explode(',', $result['Name'], 2);
        
        $profile = [
            'firstname' => trim($name[1]),
            'lastname' => ucfirst(trim($name[0])),
            'phone' => $result['MainPhone'],
            'email' => $result['MainEmail'],
            'address1' => $result['MainAddrLine1'],
            'address2' => $result['MainAddrLine2'],
            'zip' => $result['MainZip'],
            'city' => $result['MainPlace'],
            'expiration_date' => $expirationDate
        ];
        $profile = array_merge($patron, $profile);
        
        $this->putCachedData($cacheKey, $profile);

        return $profile;
    }

    /**
     * Get Patron Transactions
     *
     * This is responsible for retrieving all transactions (i.e. checked out items)
     * by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws DateException
     * @throws ILSException
     * @return array        Array of the patron's transactions on success.
     */
    public function getMyTransactions($patron)
    {
        $result = $this->makeRequest(
            ['odata', 'BorrowerLoans'],
            ['$filter' => 'BorrowerId eq ' . $patron['id']]
        );
        if (empty($result)) {
            return [];
        }
        $renewLimit = $this->config['Loans']['renewalLimit'];

        $transactions = [];
        foreach ($result as $entry) {
            $renewalCount = $entry['RenewalCount'];
            $transaction = [
                'id' => $entry['MarcRecordId'],
                'checkout_id' => $entry['Id'],
                'item_id' => $entry['ItemId'],
                'duedate' => $this->dateConverter->convertToDisplayDate(
                    'U', strtotime($entry['DueTime'])
                ),
                'dueStatus' => $entry['ServiceCode'],
                'renew' => $renewalCount,
                'renewLimit' => $renewLimit,
                'renewable' => ($renewLimit-$renewalCount) > 0,
                'message' => $entry['Notes']
            ];
            if (!empty($entry['MarcRecordTitle'])) {
                $transaction['title'] = $entry['MarcRecordTitle'];
            }

            $transactions[] = $transaction;
        }

        return $transactions;
    }

    /**
     * Get Renew Details
     *
     * @param array $checkOutDetails An array of item data
     *
     * @return string Data for use in a form field
     */
    public function getRenewDetails($checkOutDetails)
    {
        return $checkOutDetails['checkout_id'];
    }

    /**
     * Renew My Items
     *
     * Function for attempting to renew a patron's items.  The data in
     * $renewDetails['details'] is determined by getRenewDetails().
     *
     * @param array $renewDetails An array of data required for renewing items
     * including the Patron ID and an array of renewal IDS
     *
     * @return array              An array of renewal information keyed by item ID
     */
    public function renewMyItems($renewDetails)
    {
        $finalResult = ['details' => []];

        foreach ($renewDetails['details'] as $details) {
            $checkedOutId = $details;
            list($code, $result) = $this->makeRequest(
                ['odata', "BorrowerLoans($checkedOutId)", 'Default.RenewLoan'],
                false, 'POST', true
            );
            
            if ($code != 200 || $result['ServiceCode'] != 'LoanRenewed') {
                $finalResult['details'][$checkedOutId] = [
                    'item_id' => $checkedOutId,
                    'success' => false
                ];
            } else {
                $newDate = $this->dateConverter->convertToDisplayDate(
                    'U', strtotime($result['DueTime'])
                );
                $finalResult['details'][$checkedOutId] = [
                    'item_id' => $checkedOutId,
                    'success' => true,
                    'new_date' => $newDate
                ];
            }
        }
        return $finalResult;
    }

    /**
     * Get Patron Holds
     *
     * This is responsible for retrieving all holds by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws DateException
     * @throws ILSException
     * @return array        Array of the patron's holds on success.
     * @todo   Support for handling frozen and pickup location change
     */
    public function getMyHolds($patron)
    {
        $result = $this->makeRequest(
            ['odata', 'BorrowerReservations'],
            ['$filter' => 'BorrowerId eq ' . $patron['id']]
        );
        if (!isset($result)) {
            return [];
        }

        $holds = [];
        foreach ($result as $entry) {
            $hold = [
                'id' => $entry['MarcRecordId'],
                'item_id' => $entry['ItemId'],
                'location' =>
                   $this->getLibraryUnitName($entry['DeliverAtLocalUnitId']),
                'create' => $this->dateConverter->convertToDisplayDate(
                    'U', strtotime($entry['ResTime'])
                ),
                'expire' => $this->dateConverter->convertToDisplayDate(
                    'U', strtotime($entry['ResValidUntil'])
                ),
                'position' => $entry['NumberInQueue'],
                'available' => $entry['ServiceCode'] === 'ReservationArrived',
                'requestId' => $entry['Id']
            ];
            if (!empty($entry['MarcRecordTitle'])) {
                $hold['title'] = $entry['MarcRecordTitle'];
            }
            $holds[] = $hold;
        }
        return $holds;
    }

    /**
     * Place Hold
     *
     * Attempts to place a hold or recall on a particular item and returns
     * an array with result details or throws an exception on failure of support
     * classes
     *
     * @param array $holdDetails An array of item and patron data
     *
     * @throws ILSException
     * @return mixed An array of data on the request including
     * whether or not it was successful and a system message (if available)
     */
    public function placeHold($holdDetails)
    {
        $patron = $holdDetails['patron'];
        
        $pickUpLocation = !empty($holdDetails['pickUpLocation'])
            ? $holdDetails['pickUpLocation'] : $this->defaultPickUpLocation;
        $itemId = isset($holdDetails['item_id']) ? $holdDetails['item_id'] : false;
        
        // Make sure pickup location is valid
        if (!$this->pickUpLocationIsValid($pickUpLocation, $patron, $holdDetails)) {
            return $this->holdError('hold_invalid_pickup');
        }

        $request = [
            'BorrowerId' =>  $patron['id'],
            'MarcId' => $holdDetails['id'],
            'DeliverAtUnitId' => $pickUpLocation
        ];

        list($code, $result) = $this->makeRequest(
            ['odata', 'BorrowerReservations', 'Default.Create'],
            json_encode($request),
            'POST',
            true
        );

        if ($code >= 300) {
            return $this->holdError($code, $result);
        }
        return ['success' => true];
    }

    /**
     * Get Cancel Hold Details
     *
     * Get required data for canceling a hold. This value is used by relayed to the
     * cancelHolds function when the user attempts to cancel a hold.
     *
     * @param array $holdDetails An array of hold data
     *
     * @return string Data for use in a form field
     */
    public function getCancelHoldDetails($holdDetails)
    {
        return $holdDetails['available'] ? '' : $holdDetails['requestId'];
    }

    /**
     * Cancel Holds
     *
     * Attempts to Cancel a hold. The data in $cancelDetails['details'] is determined
     * by getCancelHoldDetails().
     *
     * @param array $cancelDetails An array of item and patron data
     *
     * @return array               An array of data on each request including
     * whether or not it was successful and a system message (if available)
     */
    public function cancelHolds($cancelDetails)
    {
        $details = $cancelDetails['details'];
        $count = 0;
        $response = [];

        foreach ($details as $detail) {
            list($resultCode, $result) = $this->makeRequest(
                ['odata', 'BorrowerReservations(' . $detail . ')'],
                false, 'DELETE', true
            );
            
            if ($resultCode != 204) {
                $response[$detail] = [
                    'success' => false,
                    'status' => 'hold_cancel_fail',
                    'sysMessage' => false
                ];
            } else {
                $response[$detail] = [
                    'success' => true,
                    'status' => 'hold_cancel_success'
                ];
                ++$count;
            }
        }
        return ['count' => $count, 'items' => $response];
    }

    /**
     * Get Pick Up Locations
     *
     * This is responsible for gettting a list of valid library locations for
     * holds / recall retrieval
     *
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data.  May be used to limit the pickup options
     * or may be ignored.  The driver must not add new options to the return array
     * based on this data or other areas of VuFind may behave incorrectly.
     *
     * @throws ILSException
     * @return array        An array of associative arrays with locationID and
     * locationDisplay keys
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getPickUpLocations($patron = false, $holdDetails = null)
    {
        $excluded = isset($this->config['Holds']['excludePickupLocations'])
            ? explode(':', $this->config['Holds']['excludePickupLocations']) : [];

        $units = $this->getLibraryUnits();

        $locations = [];
        foreach ($units as $key => $val) {
            if (in_array($key, $excluded) || $val['department']) {
                continue;
            }
            $locations[] = [
                'locationID' => $key,
                'locationDisplay' => $val['name']
            ];
        }

        // Do we need to sort pickup locations? If the setting is false, don't
        // bother doing any more work. If it's not set at all, default to
        // alphabetical order.
        $orderSetting = isset($this->config['Holds']['pickUpLocationOrder'])
            ? $this->config['Holds']['pickUpLocationOrder'] : 'default';
        if (count($locations) > 1 && !empty($orderSetting)) {
            $locationOrder = $orderSetting === 'default'
                ? [] : array_flip(explode(':', $orderSetting));
            $sortFunction = function ($a, $b) use ($locationOrder) {
                $aLoc = $a['locationID'];
                $bLoc = $b['locationID'];
                if (isset($locationOrder[$aLoc])) {
                    if (isset($locationOrder[$bLoc])) {
                        return $locationOrder[$aLoc] - $locationOrder[$bLoc];
                    }
                    return -1;
                }
                if (isset($locationOrder[$bLoc])) {
                    return 1;
                }
                return strcasecmp($a['locationDisplay'], $b['locationDisplay']);
            };
            usort($locations, $sortFunction);
        }

        return $locations;
    }

    /**
     * Get Default Pick Up Location
     *
     * Returns the default pick up location
     *
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data.  May be used to limit the pickup options
     * or may be ignored.
     *
     * @return false|string      The default pickup location for the patron or false
     * if the user has to choose.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getDefaultPickUpLocation($patron = false, $holdDetails = null)
    {
        return $this->defaultPickUpLocation;
    }

    /**
     * Update patron contact information
     *
     * @param array $patron  Patron array
     * @param array $details Associative array of patron contact information
     *
     * @throws ILSException
     *
     * @return array Associative array of the results
     */
    public function updateAddress($patron, $details)
    {
        $map = [
            'address1' => 'MainAddrLine1',
            'address2' => 'MainAddrLine2',
            'zip' => 'MainZip',
            'city' => 'MainPlace',
            'phone' => 'MainPhone',
            'email' => 'MainEmail'
        ];

        $request = [];
        foreach ($details as $field => $val) {
            if (!isset($map[$field])) {
                continue;
            }
            $field = $map[$field];
            $request[$field] = $val;
        }

        list($code, $result) = $this->makeRequest(
            ['odata',
             'Borrowers(' . $patron['id'] . ')'],
            json_encode($request),
            'PATCH',
            true
        );

        if ($code != 200) {
            $message = 'An error has occurred';
            return [
                'success' => false, 'status' => $message
            ];
        }
        $this->putCachedData($this->getProfileCacheKey($patron), null);

        return ['success' => true, 'status' => 'request_change_accepted'];
    }

    /**
     * Change pickup location
     *
     * This is responsible for changing the pickup location of a hold
     *
     * @param string $patron      Patron array
     * @param string $holdDetails The request details
     *
     * @return array Associative array of the results
     */
    public function changePickupLocation($patron, $holdDetails)
    {
        $requestId = $holdDetails['requestId'];
        $pickUpLocation = $holdDetails['pickupLocationId'];

        if (!$this->pickUpLocationIsValid($pickUpLocation, $patron, $holdDetails)) {
            return $this->holdError('hold_invalid_pickup');
        }

        $request = [
            'PickupUnitId' => $pickUpLocation
        ];

        list($code, $result) = $this->makeRequest(
            ['odata', 'BorrowerReservations(' . $requestId . ')',
             'Default.ChangePickupUnit'],
            json_encode($request),
            'POST',
            true
        );

        if ($code > 204) {
            return $this->holdError($code, $result);
        }
        return ['success' => true];
    }

    /**
     * Change Password
     *
     * Attempts to change patron password (PIN code)
     *
     * @param array $details An array of patron id and old and new password:
     *
     * 'patron'      The patron array from patronLogin
     * 'oldPassword' Old password
     * 'newPassword' New password
     *
     * @return array An array of data on the request including
     * whether or not it was successful and a system message (if available)
     */
    public function changePassword($details)
    {
        $request = [
            'NewPin' => $details['newPassword'],
            'OldPin' => $details['oldPassword']
        ];

        list($code, $result) = $this->makeRequest(
            ['odata',
             'Borrowers(' . $details['patron']['id'] . ')',
             'Default.ChangePinCode'],
            json_encode($request),
            'POST',
            true
        );

        if ($code != 204) {
            return [
                'success' => false,
                'status' => 'authentication_error_invalid_attributes'
            ];
        }
        return ['success' => true, 'status' => 'change_password_ok'];
    }

    /**
     * Helper method to determine whether or not a certain method can be
     * called on this driver.  Required method for any smart drivers.
     *
     * @param string $method The name of the called method.
     * @param array  $params Array of passed parameters
     *
     * @return bool True if the method can be called with the given parameters,
     * false otherwise.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function supportsMethod($method, $params)
    {
        // Special case: change password is only available if properly configured.
        if ($method == 'changePassword') {
            return isset($this->config['changePassword']);
        }
        return is_callable([$this, $method]);
    }

    /**
     * Get Item Statuses
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * @param string $id     The record id to retrieve the holdings for
     * @param array  $patron Patron information, if available
     *
     * @return array An associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber.
     */
    protected function getItemStatusesForBiblio($id, $patron = null)
    {
        $units = $this->getLibraryUnits();
        
        $result = $this->makeRequest(
            ['odata', 'CatalogueItems'],
            ['$filter' => "MarcRecordId eq $id"]
        );

        if (empty($result)) {
            return [];
        }

        $statuses = [];
        $organisationTotal = [];
        foreach ($result as $i => $item) {
            $status = $item['ItemStatus'];
            if ($status == 'Discarded') {
                continue;
            }

            $unitId = $item['BelongToUnitId'];
            if (!$unit = $this->getLibraryUnit($unitId)) {
                continue;
            }
            $organisationName = $unit['organisationName'];
            $locationName = $this->translate(
                'location_' . $unit['name'],
                null,
                $unit['name']
            );
            
            $available = $item['ItemStatus'] === 'AvailableForLoan';
            $statusCode = $this->getItemStatusCode($item);

            if (isset($organisationTotal[$unitId])) {
                $organisationTotal[$unitId]['total']++;
                if ($available) {
                    $organisationTotal[$unitId]['available']++;
                }
                $organisationTotal[$unitId]['reservations']
                    += $item['ReservationQueueLength'];
                continue;
            }

            $organisationTotal[$unit['branch']] = [
               'available' => $available ? 1 : 0,
               'total' => 1,
               'reservations' => $item['ReservationQueueLength'],
               'displayText' => $statusCode
            ];

            $unit = $this->getLibraryUnit($unitId);
            
            $entry = [
                'id' => $id,
                'item_id' => $item['Id'],
                'parentId' => $unit['parent'],
                'holdings_id' => $unit['organisation'],
                'location' => $organisationName,
                'organisation_id' => $unit['organisation'],
                'branch' => $locationName,
                'branch_id' => $unit['branch'],
                'availability' => $available,
                'status' => $statusCode,
                'status_array' => [$statusCode],
                'reserve' => 'N',
                'callnumber' => $item['Shelf'],
                'duedate' => null,
                'barcode' => $item['Barcode'],
                'item_notes' => [isset($items['notes']) ? $item['notes'] : null],
                'is_holdable' =>  false
            ];

            $statuses[] = $entry;
        }

        foreach ($statuses as &$status) {
            $status['availabilityInfo']
                = array_merge(
                    ['displayText' => $status['status']],
                    $organisationTotal[$status['branch_id']]
                );
        }

        usort($statuses, [$this, 'statusSortFunction']);

        $summary = $this->getHoldingsSummary($statuses);
        $statuses[] = $summary;
        
        return $statuses;
    }

    /**
     * Return summary of holdings items.
     *
     * @param array $holdings Parsed holdings items
     *
     * @return array summary
     */
    protected function getHoldingsSummary($holdings)
    {
        $holdable = false;
        $availableTotal = $itemsTotal = $orderedTotal = $reservationsTotal = 0;
        $locations = [];
        foreach ($holdings as $item) {
            if (!empty($item['availability'])) {
                $availableTotal++;
            }
            $itemsTotal += $item['availabilityInfo']['total'];
            
            if (isset($item['availabilityInfo']['ordered'])) {
                $orderedTotal += $item['availabilityInfo']['ordered'];
            }
            
            $reservationsTotal += $item['availabilityInfo']['reservations'];
            
            $locations[$item['location']] = true;

            if ($item['is_holdable']) {
                $holdable = true;
            }
        }

        // Since summary data is appended to the holdings array as a fake item,
        // we need to add a few dummy-fields that VuFind expects to be
        // defined for all elements.
        return [
           'available' => $availableTotal,
           'ordered' => $orderedTotal,
           'total' => $itemsTotal,
           'reservations' => $reservationsTotal,
           'locations' => count($locations),
           'holdable' => $holdable,
           'availability' => null,
           'callnumber' => null,
           'location' => null
        ];
    }

    /**
     * Map Mikromarc status to VuFind.
     *
     * @param array $item Item from Mikromarc.
     *
     * @return String Status
     */
    protected function getItemStatusCode($item)
    {
        $map = [
           'AvailableForLoan' => 'On Shelf',
           'InCourseOfAcquisition' => 'Ordered',
           'OnLoan' => 'Charged',
           'InProcess' => 'In Process',
           'Recalled' => 'Recall Request',
           'WaitingOnReservationShelf' => 'On Holdshelf',
           'AwaitingReplacing' => 'In Repair',
           'InTransitBetweenLibraries' => 'In Transit',
           'ClaimedReturnedOrNeverBorrowed' => 'Claims Returned',
           'Lost' => 'Lost--Library Applied',
           'MissingBeingTraced' => 'Lost--Library Applied',
           'AtBinding' => 'In Repair',
           'UnderRepair' => 'In Repair',
           'AwaitingTransfer' => 'In Transit',
           'MissingOverdue' => 'Overdue',
           'Withdrawn' => 'Withdrawn',
           'Discarded' => 'Withdrawn',
           'Other' => 'Not Available',
           'Unknown' => 'No information available',
           'OrderedFromAnotherLibrary' => 'In Transit',
           'DeletedInMikromarc1' => 'Withdrawn',
           'Reserved' => 'On Hold',
           'ReservedInTransitBetweenLibraries' => 'In Transit On Hold',
           'ToAcquisition' => 'In Process',
        ];
        
        return isset($map[$item['ItemStatus']])
            ? $map[$item['ItemStatus']] : 'No information available';
    }

    /**
     * Get the list of library units.
     *
     * @return array Associative array of library unit id => name pairs.
     */
    protected function getLibraryUnits()
    {
        $cacheKey = implode(
            '|', [
               'mikromarc', 'libraryUnits',
               $this->config['Catalog']['base'], $this->config['Catalog']['unit']
            ]
        ); 
        
        $units = $this->getCachedData($cacheKey);

        if ($units !== null) {
            return $units;
        }
            
        $result = $this->makeRequest(['odata', 'LibraryUnits']);

        $units = [];
        foreach ($result as $unit) {
            $id = $unit['Id'];
            $units[$id] = [
                'id' => $id,
                'name' => $unit['Name'],
                'parent' => $unit['ParentUnitId'],
                'department' => $unit['IsDepartment']
            ];
        }
        
        foreach ($units as $key => &$unit) {
            $parent = !empty($units[$unit['parent']])
                ? $units[$unit['parent']] : null;

            // Branch and organisation
            $unit['branch'] = $key;
            $organisationId = 1;
            $organisationName = null;
            if (!empty($this->config['Holdings']['organisationId'])) {
                $organisationId = $this->config['Holdings']['organisationId'];
                $organisationName
                    = $this->translator->translate("source_$organisationId");
            } else if ($parent && $parent['department']) {
                $organisationId = $parent['parent'];
                $organisationName = $this->getLibraryUnit($parent['id'])['name'];
            }
            
            $unit['organisation'] = $organisationId;
            $unit['organisationName'] = $organisationName;
            
            if (!$unit['department'] || !$parent) {
                continue;
            }

            // Prepend parent name to department names
            $parentName = $parent['name'];
            $unitName = $unit['name'];
            if (strpos(trim($unitName), trim($parentName)) === 0) {
                continue;
            }
            $unit['name'] = "$parentName - $unitName";
        }

        error_log(var_export($units, true));
        $this->putCachedData($cacheKey, $units);
        
        return $units;
    }

    /**
     * Return library unit information..
     *
     * @param int $id Unit id.
     *
     * @return array|null
     */
    protected function getLibraryUnit($id)
    {
        $units = $this->getLibraryUnits();
        return isset($units[$id]) ? $units[$id] : null;
    }

    /**
     * Return library unit name.
     *
     * @param int $id Unit id.
     *
     * @return string|null
     */
    protected function getLibraryUnitName($id)
    {
        $unit = $this->getLibraryUnit($id);
        return $unit ? $unit['name'] : null;
    }
    
    /**
     * Get patron's blocks, if any
     *
     * @param array $patron Patron
     *
     * @return mixed        A boolean false if no blocks are in place and an array
     * of block reasons if blocks are in place
     */
    protected function getPatronBlocks($patron)
    {
        return false;
    }

    /**
     * Get cache key for patron profile.
     *
     * @param array $patron Patron
     *
     * @return string
     */
    protected function getProfileCacheKey($patron)
    {
        return 'mikromarc|profile|'
            . md5(implode('|', [$patron['cat_username'], $patron['cat_password']]));
    }
    
    /**
     * Create a HTTP client
     *
     * @param string $url Request URL
     *
     * @return \Zend\Http\Client
     */
    protected function createHttpClient($url)
    {
        $client = $this->httpService->createClient($url);

        if (isset($this->config['Http']['ssl_verify_peer_name'])
            && !$this->config['Http']['ssl_verify_peer_name']
        ) {
            $adapter = $client->getAdapter();
            if ($adapter instanceof \Zend\Http\Client\Adapter\Socket) {
                $context = $adapter->getStreamContext();
                $res = stream_context_set_option(
                    $context, 'ssl', 'verify_peer_name', false
                );
                if (!$res) {
                    throw new \Exception('Unable to set sslverifypeername option');
                }
            } elseif ($adapter instanceof \Zend\Http\Client\Adapter\Curl) {
                $adapter->setCurlOption(CURLOPT_SSL_VERIFYHOST, false);
            }
        }

        // Set timeout value
        $timeout = isset($this->config['Catalog']['http_timeout'])
            ? $this->config['Catalog']['http_timeout'] : 30;
        $client->setOptions(
            ['timeout' => $timeout, 'useragent' => 'VuFind', 'keepalive' => true]
        );

        // Set Accept header
        $client->getRequest()->getHeaders()->addHeaderLine(
            'Accept', 'application/json'
        );

        return $client;
    }

    /**
     * Check if an item is holdable
     *
     * @param array $item Item
     *
     * @return bool
     */
    protected function itemHoldAllowed($item)
    {
        return false;
    }

    /**
     * Is the selected pickup location valid for the hold?
     *
     * @param string $pickUpLocation Selected pickup location
     * @param array  $patron         Patron information returned by the patronLogin
     * method.
     * @param array  $holdDetails    Details of hold being placed
     *
     * @return bool
     */
    protected function pickUpLocationIsValid($pickUpLocation, $patron, $holdDetails)
    {
        $pickUpLibs = $this->getPickUpLocations($patron, $holdDetails);
        foreach ($pickUpLibs as $location) {
            if ($location['locationID'] == $pickUpLocation) {
                return true;
            }
        }
        return false;
    }

    /**
     * Return a hold error message
     *
     * @param int   $code   HTTP Result Code
     * @param array $result API Response
     *
     * @return array
     */
    protected function holdError($code, $result)
    {
        $message = 'hold_error_fail';
        if (!empty($result['error']['message'])) {
            $message = $result['error']['message'];
        } else if (!empty($result['error']['code'])) {
            $message = $result['error']['code'];
        }

        $map = [
           'DuplicateReservationExists' => 'hold_error_duplicate'
        ];

        if (isset($map[$message])) {
            $message = $map[$message];
        }
        
        return [
            'success' => false,
            'sysMessage' => $message
        ];
    }

    /**
     * Make Request
     *
     * Makes a request to the Mikromarc REST API
     *
     * @param array  $hierarchy  Array of values to embed in the URL path of
     * the request
     * @param array  $params     A keyed array of query data
     * @param string $method     The http request method to use (Default is GET)
     * @param bool   $returnCode If true, returns HTTP status code in addition to
     * the result
     *
     * @throws ILSException
     * @return mixed JSON response decoded to an associative array or null on
     * authentication error
     */
    protected function makeRequest($hierarchy, $params = false, $method = 'GET',
        $returnCode = false
    ) {
        // Set up the request
        $conf = $this->config['Catalog'];
        $apiUrl = $this->config['Catalog']['host'];
        $apiUrl .= '/' . urlencode($conf['base']);
        $apiUrl .= '/' . urlencode($conf['unit']);
        
        // Add hierarchy
        foreach ($hierarchy as $value) {
            $apiUrl .= '/' . urlencode($value);
        }

        // Create proxy request
        $client = $this->createHttpClient($apiUrl);
        $client->setAuth($conf['username'], $conf['password']);

        // Add params
        if (false !== $params) {
            if ($method == 'GET') {
                $client->setParameterGet($params);
            } else {
                if (is_string($params)) {
                    $client->getRequest()->setContent($params);
                    $client->getRequest()->getHeaders()
                        ->addHeaderLine('Content-Type', 'application/json');
                } else {
                    $client->setParameterPost($params);
                }
            }
        } else {
            $client->setHeaders(['Content-length' => 0]);
        }

        // Send request and retrieve response
        $startTime = microtime(true);
        $client->setMethod($method);

        // Result pagination.
        $page = 0;
        $maxPages = 10;
        if (false == $params) {
            $params = [];
        }

        $data = [];
        while (true && $page < $maxPages) {
            // Append '$skip' parameter straight to the url
            // so that Zend does not urlencode the $-sign (this would break
            // the pagination).

            $client->setUri($apiUrl);
            if ($page > 0) {
                $client->setUri($apiUrl . '?$skip=' . $page*100);
            }

            $response = $client->send();

            $result = $response->getBody();

            $fullUrl = $apiUrl;
            if ($method == 'GET') {
                $fullUrl .= ($page ? '&' : '?')
                    . $client->getRequest()->getQuery()->toString();
            }
            
            $this->debug(
                '[' . round(microtime(true) - $startTime, 4) . 's]'
                . " GET request $fullUrl" . PHP_EOL . 'response: ' . PHP_EOL
                . $result
            );
            
            // Handle errors as complete failures only if the API call didn't return
            // valid JSON that the caller can handle
            $decodedResult = json_decode($result, true);
            
            if (!$response->isSuccess()
                && (null === $decodedResult || !empty($decodedResult['error']))
                && !$returnCode
            ) {
                $params = $method == 'GET'
                   ? $client->getRequest()->getQuery()->toString()
                    : $client->getRequest()->getPost()->toString();
                $this->error(
                    "$method request for '$apiUrl' with params"
                    . "'$params' and contents '"
                    . $client->getRequest()->getContent() . "' failed: "
                    . $response->getStatusCode() . ': '
                    . $response->getReasonPhrase()
                    . ', response content: ' . $response->getBody()
                );
                throw new ILSException('Problem with Mikromarc REST API.');
            }

            // More results available?
            $next = !empty($decodedResult['@odata.nextLink']);
            
            if (isset($decodedResult['value'])) {
                $decodedResult = $decodedResult['value'];
            }

            if (!$next && $page == 0) {
                $data = $decodedResult;
            } else if ($next) {    
                $data = array_merge($data, $decodedResult);
            }
            
            if (!$next) {
                break;
            }

            $page++;
        }

        return $returnCode ? [$response->getStatusCode(), $data]
            : $data;
    }

    /**
     * Status item sort function
     *
     * @param array $a First status record to compare
     * @param array $b Second status record to compare
     *
     * @return int
     */
    protected function statusSortFunction($a, $b)
    {
        $key = 'parentId';

        $sortOrder = $this->holdingsOrganisationOrder;
        $orderA = in_array($a[$key], $sortOrder)
            ? array_search($a[$key], $sortOrder) : null;
        $orderB = in_array($b[$key], $sortOrder)
            ? array_search($b[$key], $sortOrder) : null;

        if ($orderA !== null) {
            if ($orderB !== null) {
                $posA = array_search($a[$key], $sortOrder);
                $posB = array_search($b[$key], $sortOrder);
                return $posA-$posB;
            }
            return -1;
        }
        if ($orderB !== null) {
            return 1;
        }

        return strcmp($a['branch'], $b['branch']);
    }
}

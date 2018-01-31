<?php
/**
 * Record driver data formatting view helper
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2016.
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
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:architecture:record_data_formatter
 * Wiki
 */
namespace Finna\View\Helper\Root;

/**
 * Record driver data formatting view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:architecture:record_data_formatter
 * Wiki
 */
class RecordDataFormatter extends \VuFind\View\Helper\Root\RecordDataFormatter
{
    /**
     * Filter unnecessary fields from Marc records.
     *
     * @param array $coreFields data to filter.
     *
     * @return array
     */
    public function filterMarcFields($coreFields)
    {
        $filter = [
            'Contributors', 'Extent', 'Format', 'Organisation', 'Published',
            'Online Access', 'Original Work', 'Assistants', 'Authors', 'Music',
            'Press Reviews', 'mainFormat', 'Access Restrictions', 'Edition',
            'Archive', 'Archive Series', 'Archive Origination',
            'Item Description FWD', 'Published in', 'Source Collection'
        ];
        foreach ($filter as $key) {
            unset($coreFields[$key]);
        }
        return $coreFields;
    }

    /**
     * Filter unnecessary fields from Lido records.
     *
     * @param array $coreFields data to filter.
     *
     * @return array
     */
    public function filterLidoFields($coreFields)
    {
        $filter = [
            'Contributors', 'Extent', 'Published', 'Online Access',
            'Original Work', 'Assistants', 'Authors', 'Music',
            'Press Reviews', 'Publisher', 'Access Restrictions', 'Unit ID',
            'Other Titles', 'Archive', 'Access', 'Item Description FWD',
            'Publish date', 'Source Collection'
        ];
        foreach ($filter as $key) {
            unset($coreFields[$key]);
        }
        return $coreFields;
    }

    /**
     * Filter unnecessary fields from QDC records.
     *
     * @param array $coreFields data to filter.
     *
     * @return array
     */
    public function filterQDCFields($coreFields)
    {
        $filter = [
            'Contributors', 'Extent', 'Format', 'Organisation', 'Published',
            'Online Access', 'Original Work', 'Assistants', 'Authors', 'Music',
            'Press Reviews', 'Publisher', 'Access Restrictions', 'mainFormat',
            'Archive', 'Item Description FWD', 'Publish date', 'Source Collection',
            'ISBN'
        ];
        foreach ($filter as $key) {
            unset($coreFields[$key]);
        }
        return $coreFields;
    }

    /**
     * Filter unnecessary fields from EAD records.
     *
     * @param array $coreFields data to filter.
     *
     * @return array
     */
    public function filterEADFields($coreFields)
    {
        $filter = [
            'Contributors', 'Organisation', 'Inventory ID', 'Online Access',
            'Access', 'Item Description FWD', 'Physical Description',
            'Published in', 'Published', 'Source Collection'
        ];
        foreach ($filter as $key) {
            unset($coreFields[$key]);
        }
        return $coreFields;
    }

    /**
     * Filter unnecessary fields from Primo records.
     *
     * @param array $coreFields data to filter.
     *
     * @return array
     */
    public function filterPrimoFields($coreFields)
    {
        $filter = [
            'Contributors', 'Extent', 'Archive', 'Publisher', 'Organisation',
            'Item Description FWD', 'Published in', 'Published', 'Description',
            'Format', 'Online Access', 'Access Restrictions'
        ];
        foreach ($filter as $key) {
            unset($coreFields[$key]);
        }
        return $coreFields;
    }

    /**
     * Filter unnecessary fields from Primo records.
     *
     * @param array $coreFields data to filter.
     *
     * @return array
     */
    public function filterForwardFields($coreFields)
    {
        $filter = [
            'Publisher','Edition', 'Extent', 'Archive', 'Published in', 'Format',
            'Other Titles', 'Presenters', 'Organisation', 'Published', 'Authors',
            'Access Restrictions', 'Item Description', 'Publisher',
            'Source Collection', 'Music', 'Distribution', 'Press Reviews',
            'Inspection Details', 'Item Description FWD', 'Description'
        ];
        foreach ($filter as $key) {
            unset($coreFields[$key]);
        }
        return $coreFields;
    }

    /**
     * Filter unnecessary fields from EAD records.
     *
     * @param array $coreFields data to filter.
     *
     * @return array
     */
    public function filterForCollection($coreFields)
    {
        $filter = [
            'Contributors', 'Organisation', 'Inventory ID', 'Online Access',
            'Access', 'Item Description FWD', 'Physical Description',
            'Published in', 'Published', 'Source Collection', 'Format'
        ];
        foreach ($filter as $key) {
            unset($coreFields[$key]);
        }
        return $coreFields;
    }
}

<?php
/**
 * Record loader
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2016.
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
 * @category VuFind2
 * @package  Record
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Record;
use VuFind\RecordDriver\PluginManager as RecordFactory,
    VuFindSearch\Service as SearchService,
    VuFind\Record\Cache,
    Finna\Db\Table\Resource,
    Finna\RecordDriver\MetaLib;

/**
 * Record loader
 *
 * @category VuFind2
 * @package  Record
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Loader extends \VuFind\Record\Loader
{
    /**
     * Record cache
     *
     * @var Cache
     */
    protected $tableManager;

    /**
     * Constructor
     *
     * @param SearchService $searchService Search service
     * @param RecordFactory $recordFactory Record loader
     * @param Cache         $recordCache   Record Cache
     */
    public function __construct(SearchService $searchService,
        RecordFactory $recordFactory, $tableManager, Cache $recordCache = null
    ) {
        $this->tableManager = $tableManager;
        return parent::__construct($searchService, $recordFactory, $recordCache);
    }

    /**
     * Given an ID and record source, load the requested record object.
     *
     * @param string $id              Record ID
     * @param string $source          Record source
     * @param bool   $tolerateMissing Should we load a "Missing" placeholder
     * instead of throwing an exception if the record cannot be found?
     *
     * @throws \Exception
     * @return \VuFind\RecordDriver\AbstractBase
     */
    public function load($id, $source = DEFAULT_SEARCH_BACKEND,
        $tolerateMissing = false
    ) {
        if ($source == 'MetaLib') {
            $records = $this->loadMetaLibRecords([$id]);
            return $records[$id];
        }
        return parent::load($id, $source);
    }

    /**
     * Given an array of associative arrays with id and source keys (or pipe-
     * separated source|id strings), load all of the requested records in the
     * requested order.
     *
     * @param array $ids Array of associative arrays with id/source keys or
     * strings in source|id format.  In associative array formats, there is
     * also an optional "extra_fields" key which can be used to pass in data
     * formatted as if it belongs to the Solr schema; this is used to create
     * a mock driver object if the real data source is unavailable.
     *
     * @throws \Exception
     * @return array     Array of record drivers
     */
    public function loadBatch($ids)
    {
        // Separate MetaLib ids that are loaded separately
        $accept = $discard = $metalibIds = $recIds = [];
        foreach ($ids as $key => $data) {
            if (!is_array($data)) {
                $parts = explode('|', $data, 2);
                $data = ['source' => $parts[0], 'id' => $parts[1]];
            }
            $recId = $data['id'];
            $metalib = isset($data['source']) && $data['source'] == 'MetaLib';
            if ($metalib) {
                $discard[] = $data;
                $metalibIds[] = $recId;
            } else {
                $accept[] = $data;
            }
            $recIds[] = $recId;
        }

        $result = [];

        $records = parent::loadBatch($accept);        
        $metalibRecords = !empty($metalibIds)
            ? $this->loadMetaLibRecords($metalibIds) : [];

        foreach ($recIds as $recId) {
            if (isset($metalibRecords[$recId])) {
                $result[] = $metalibRecords[$recId];
            } else {
                $result[] = array_shift($records);
            }
        }
        
        return $result;
    }

    /**
     * Load MetaLib records from the resource table.
     *
     * @param array $ids Record ids
     *
     * @return array Array of record drivers
     */    
    protected function loadMetaLibRecords($ids)
    {
        $callback = function ($select) use ($ids) {
            $select->where->in('record_id', $ids);
        };
        $result = [];
        $metalibResources 
            = $this->tableManager->get('resource')->select($callback);

        if ($metalibResources) {
            foreach ($metalibResources as $resource) {
                $data = [
                    'id' => $resource->record_id,              
                    'title' => $resource->title, 
                    'publishDate' => $resource->year, 
                    'author' => $resource->author
                ];
                $driver = new Metalib();
                $driver->setRawData($data);
                $driver->setDbTableManager($this->tableManager);
                $result[$resource->record_id] = $driver;
            }
        }
        return $result;
    }
}

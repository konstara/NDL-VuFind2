<?php
/**
 * Records Controller
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
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace Finna\Controller;

/**
 * Records Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class RecordsController extends \VuFind\Controller\RecordsController
{
    /**
     * Home action -- call standard results action
     *
     * @return mixed
     */
    public function homeAction()
    {
        // Handle displaying of a single MetaLib record via results-action.
        // (VuFind would redirect to /MetaLibRecord/[id] which is deprecated)
        $ids = $this->params()->fromQuery('id', []);
        if (count($ids) == 1) {
            $parts = explode('|', $ids[0], 2);
            if (count($parts) == 2 && $parts[0] === 'MetaLib') {
                return parent::resultsAction();
            }
        }
        return parent::homeAction();
    }
}

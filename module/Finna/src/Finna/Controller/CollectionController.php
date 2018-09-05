<?php
/**
 * Collection Controller
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2017-2018.
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
 * @package  Controller
 * @author   Anna Niku <anna.niku@gofore.com>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Controller;

/**
 * Collection Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Anna Niku <anna.niku@gofore.com>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class CollectionController extends \VuFind\Controller\CollectionController
{
    use CatalogLoginTrait;

    /**
     * Display a particular tab.
     *
     * @param string $tab  Name of tab to display
     * @param bool   $ajax Are we in AJAX mode?
     *
     * @return mixed
     */
    protected function showTab($tab, $ajax = false)
    {
        // Call for login modal
        if ($this->params()->fromQuery('layout', 'false') == 'lightbox'
            && $this->params()->fromQuery('catalogLogin', 'false') == 'true'
        ) {
            return $this->catalogLogin();
        }

        $view = parent::showTab($tab, $ajax);

        $this->getSearchMemory()->rememberScrollData($view->scrollData);
        return $view;
    }
}

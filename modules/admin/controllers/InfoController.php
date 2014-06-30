<?php
/**
 * This file is part of OPUS. The software OPUS has been originally developed
 * at the University of Stuttgart with funding from the German Research Net,
 * the Federal Department of Higher Education and Research and the Ministry
 * of Science, Research and the Arts of the State of Baden-Wuerttemberg.
 *
 * OPUS 4 is a complete rewrite of the original OPUS software and was developed
 * by the Stuttgart University Library, the Library Service Center
 * Baden-Wuerttemberg, the Cooperative Library Network Berlin-Brandenburg,
 * the Saarland University and State Library, the Saxon State Library -
 * Dresden State and University Library, the Bielefeld University Library and
 * the University Library of Hamburg University of Technology with funding from
 * the German Research Foundation and the European Regional Development Fund.
 *
 * LICENCE
 * OPUS is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the Licence, or any later version.
 * OPUS is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
 * details. You should have received a copy of the GNU General Public License
 * along with OPUS; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

/**
 * Controller für die Anzeige von Informationen zur Konfiguration von OPUS und dem System auf dem es läuft.
 *
 * @category    Application
 * @package     Module_Admin
 * @author      Jens Schwidder <schwidder@zib.de>
 * @author      Michael Lang   <lang@zib.de>
 * @copyright   Copyright (c) 2008-2014, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 * @version     $Id$
 */
class Admin_InfoController extends Controller_Action {

    public function indexAction() {
        $config = Zend_Registry::get('Zend_Config');

        if (isset($config->publish->maxfilesize)) {
            $this->view->maxfilesize = $config->publish->maxfilesize;
        } else {
            $this->view->maxfilesize = $this->view->translate('admin_info_error_not_set');
        }
        $this->view->postMaxSize = ini_get('post_max_size');
        $this->view->uploadMaxFilesize = ini_get('upload_max_filesize');
    }

    public function updateAction() {
        $this->view->latestVersionLabel = "";
        $this->view->versionUpdate = "";
        $this->compareVersion();
    }

    private function compareVersion() {
        $localVersion = Zend_Registry::get('Zend_Config')->version;
        $latestVersion = $this->_helper->version();

        if (is_null($localVersion)) {
            throw new Exception( 'admin_info_local_Version_File_Not_Readable' );
        }
        if (is_null($latestVersion)) {
            throw new Exception( 'admin_info_server_Version_File_Not_Readable' );
        }

        if ($localVersion == $latestVersion) {
            $this->view->versionLabel = 'version_latest';
        }
        else {
            $this->view->latestVersionLabel = "version_get_latest";
            $this->view->latestVersion = $latestVersion;
            $this->view->versionLabel = 'version_outdated';
            $this->view->versionUpdate = 'version_get_update';
        }
    }

}

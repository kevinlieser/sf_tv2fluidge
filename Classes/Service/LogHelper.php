<?php

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2016 Torben Hansen <derhansen@gmail.com>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Helper class for handling logs
 */
namespace Sf\SfTv2fluidge\Service;

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Helper class for handling TV content column migration to Fluid backend layouts
 */
class LogHelper implements \TYPO3\CMS\Core\SingletonInterface {

    /**
     * @var array
     */
    protected $extConf = array();

    /**
     * Constructor
     */
    public function __construct() {
        if (!empty($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['sf_tv2fluidge'])) {
            $this->extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['sf_tv2fluidge']);
        }
    }

    /**
     * Saves the given message to the logfile, if logging is enabled
     *
     * @param $message
     * @return void
     */
    public function logMessage($message)
    {
        if ((bool)$this->extConf['enableLogging']) {
            $logfile = fopen($this->extConf['logfilePath'], 'a');
            fputs($logfile,
                date('d.m.Y H:i:s', time()) . ': ' . $message . PHP_EOL
            );
            fclose($logfile);
        }
    }

}

?> 
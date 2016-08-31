<?php

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Torben Hansen <derhansen@gmail.com>, Skyfillers GmbH
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
 * Helper class for handling unreferenced elements
 */
class Tx_SfTv2fluidge_Service_UnreferencedElementHelper implements t3lib_Singleton {

	/**
	 * @var Tx_SfTv2fluidge_Service_SharedHelper
	 */
	protected $sharedHelper;

	/**
	 * DI for shared helper
	 *
	 * @param Tx_SfTv2fluidge_Service_SharedHelper $sharedHelper
	 * @return void
	 */
	public function injectSharedHelper(Tx_SfTv2fluidge_Service_SharedHelper $sharedHelper) {
		$this->sharedHelper = $sharedHelper;
	}

    /**
     * @var Tx_SfTv2fluidge_Service_LogHelper
     */
    protected $logHelper;

    /**
     * DI for shared helper
     *
     * @param Tx_SfTv2fluidge_Service_LogHelper $logHelper
     * @return void
     */
    public function injectLogHelper(Tx_SfTv2fluidge_Service_LogHelper $logHelper) {
        $this->logHelper = $logHelper;
    }

	/**
	 * Marks all unreferenced element records as deleted with the recursion level set in the extension setting
	 *
	 * @param bool $markAsNegativeColPos
     * @param bool $ignoreShortcutPages
	 * @return int Number of records deleted
	 */
	public function markDeletedUnreferencedElementsRecords($markAsNegativeColPos = FALSE, $ignoreShortcutPages = FALSE) {
        $pids = $this->sharedHelper->getPageIds();
		$allReferencedElementsArr = array();

        // Handle page types, that can be ignored
        $ignorePageTypes = array();
        if ($ignoreShortcutPages) {
            // doktype 4 (Shortcut) - Add more doktypes if required
            $ignorePageTypes = array(4);
        }

        // Array which holds all PIDs to be processed when processing unreferenced content elements
        $processPids = array();

		foreach ($pids as $pid) {
			$pageRecord = $this->sharedHelper->getPage($pid);
			if (!empty($pageRecord) && !in_array(intval($pageRecord['doktype']), $ignorePageTypes)) {
                // Add the PID to the array of PIDs to be processed
                array_push($processPids, $pid);
                $contentTree = $this->sharedHelper->getTemplavoilaAPIObj()->getContentTree('pages', $pageRecord, FALSE);
                $referencedElementsArrAsKeys = $contentTree['contentElementUsage'];
                if (!empty($referencedElementsArrAsKeys)) {
                    $referencedElementsArr = array_keys($referencedElementsArrAsKeys);
                    $allReferencedElementsArr = array_merge($allReferencedElementsArr, $referencedElementsArr);
                }
            }
		}
		$allReferencedElementsArr = array_unique($allReferencedElementsArr);
		$allRecordUids = $this->getUnreferencedElementsRecords($allReferencedElementsArr, $processPids);
		$countRecords = count($allRecordUids);

        // Only process when we have records to be deleted
        if ($countRecords > 0) {
            if ($markAsNegativeColPos) {
                $this->markNegativeColPos($allRecordUids);
            } else {
                $this->markDeleted($allRecordUids);
            }
        }

		return $countRecords;
	}

	/**
	 * Returns an array of content UIDs which are not referenced on
	 * the any of the given pages in $pageIds
	 *
	 * @param	array		$allReferencedElementsArr: Array with UIDs of referenced elements
     * @param   array       $pageIds Array of pages where to search for unreferenced elements
	 * @return	array		Array with UIDs of tt_content records
	 * @access	protected
	 */
	function getUnreferencedElementsRecords($allReferencedElementsArr, $pageIds) {
		global $TYPO3_DB, $BE_USER;

		$elementRecordsArr = array();

		$res = $TYPO3_DB->exec_SELECTquery (
			'uid',
			'tt_content',
			'uid NOT IN (' . implode(',', $allReferencedElementsArr) . ')'.
            ' AND pid IN (' . implode(',', $pageIds) . ')' .
			' AND t3ver_wsid='.intval($BE_USER->workspace).
			t3lib_BEfunc::deleteClause('tt_content').
			t3lib_BEfunc::versioningPlaceholderClause('tt_content'),
			'',
			'sorting'
		);

		if ($res) {
			while(($elementRecordArr = $TYPO3_DB->sql_fetch_assoc($res)) !== FALSE) {
				$elementRecordsArr[] = $elementRecordArr['uid'];
			}
		}
		return $elementRecordsArr;
	}

	/**
	 * Marks the records with the given UIDs as deleted
	 *
	 * @param $uids
	 * @return void
	 */
	private function markDeleted($uids) {
		$where = 'uid IN (' . implode(',', $uids) . ')';
		$GLOBALS['TYPO3_DB']->exec_UPDATEquery('tt_content', $where, array('deleted' => 1, 'tstamp' => time()));

		$this->logHelper->logMessage('===== ' . __CLASS__ . ' - ' . __FUNCTION__ . ' =====');
		$this->logHelper->logMessage($GLOBALS['TYPO3_DB']->debug_lastBuiltQuery);
	}

	/**
	 * Marks the records with the given UIDs as using negative colPos
	 *
	 * @param $uids
	 * @return void
	 */
	private function markNegativeColPos($uids) {
		$where = 'uid IN (' . implode(',', $uids) . ')';
		$GLOBALS['TYPO3_DB']->exec_UPDATEquery('tt_content', $where, array('colPos' => -1, 'tstamp' => time()));

		$this->logHelper->logMessage('===== ' . __CLASS__ . ' - ' . __FUNCTION__ . ' =====');
		$this->logHelper->logMessage($GLOBALS['TYPO3_DB']->debug_lastBuiltQuery);
	}
}

?>
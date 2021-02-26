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

namespace Sf\SfTv2fluidge\Service;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use GridElementsTeam\Gridelements\Backend\LayoutSetup;

/**
 * Helper class for handling TV FCE to Grid Element content migration
 */
class MigrateFceHelper implements \TYPO3\CMS\Core\SingletonInterface {

	/**
	 * @var \Sf\SfTv2fluidge\Service\SharedHelper
	 */
	protected $sharedHelper;

	/**
	 * @var \TYPO3\CMS\Core\Database\ReferenceIndex
	 */
	protected $refIndex;

	/**
	 * @var \GridElementsTeam\Gridelements\Backend\LayoutSetup
	 */
	protected $layoutSetup;

	/**
	 * @var array
	 */
	protected $gridElementLayoutSetup = NULL;

	/**
	 * DI for shared helper
	 *
	 * @param \Sf\SfTv2fluidge\Service\SharedHelper $sharedHelper
	 * @return void
	 */
	public function injectSharedHelper(\Sf\SfTv2fluidge\Service\SharedHelper $sharedHelper) {
		$this->sharedHelper = $sharedHelper;
	}

	/**
	 * DI for \TYPO3\CMS\Core\Database\ReferenceIndex
	 *
	 * @param \TYPO3\CMS\Core\Database\ReferenceIndex ReferenceIndex
	 * @return void
	 */
	public function injectRefIndex(\TYPO3\CMS\Core\Database\ReferenceIndex $refIndex) {
		$this->refIndex = $refIndex;
	}

	/**
	 * Returns an array of all TemplaVoila flexible content elements stored as file
	 *
	 * @return array
	 */
	public function getAllFileFce() {
		$extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['templavoila']);
		\Extension\Templavoila\Utility\StaticDataStructure\ToolsUtility::readStaticDsFilesIntoArray($extConf);
		$staticDsFiles = array();
		foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['templavoila']['staticDataStructures'] as $staticDataStructure) {
			if ($staticDataStructure['scope'] == \Extension\Templavoila\Domain\Model\AbstractDataStructure::SCOPE_FCE) {
				$staticDsFiles[] = $staticDataStructure['path'];
			}
		}
		$quotedStaticDsFiles = $GLOBALS['TYPO3_DB']->fullQuoteArray($staticDsFiles, 'tx_templavoilaplus_tmplobj');

		$fields = 'tx_templavoilaplus_tmplobj.uid, tx_templavoilaplus_tmplobj.title';
		$table = 'tx_templavoilaplus_tmplobj';
		$where = 'tx_templavoilaplus_tmplobj.datastructure IN(' . implode(',', $quotedStaticDsFiles) . ')
			AND tx_templavoilaplus_tmplobj.deleted=0';

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($fields, $table, $where, '', '', '');

		$fces = array();
		foreach($res as $fce) {
			$fces[$fce['uid']] = $fce['title'];
		}

		return $fces;
	}

	/**
	 * Returns an array of all TemplaVoila flexible content elements stored in database
	 *
	 * @return array
	 */
	public function getAllDbFce() {
		$fields = 'tx_templavoilaplus_tmplobj.uid, tx_templavoilaplus_tmplobj.title';
		$table = 'tx_templavoilaplus_datastructure, tx_templavoilaplus_tmplobj';
		$where = 'tx_templavoilaplus_datastructure.scope=2 AND tx_templavoilaplus_datastructure.uid = tx_templavoilaplus_tmplobj.datastructure
			AND tx_templavoilaplus_datastructure.deleted=0 AND tx_templavoilaplus_tmplobj.deleted=0';

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($fields, $table, $where, '', '', '');

		$fces = array();
		foreach($res as $fce) {
			$fces[$fce['uid']] = $fce['title'];
		}

		return $fces;
	}

	/**
	 * Returns an array of all Grid Elements
	 *
	 * @return array
	 */
	public function getAllGe() {

		$gridElements = array();
		$layoutSetup = $this->getGridElementLayoutSetup();

		foreach ($layoutSetup as $layoutId => $setup) {
			$gridElements[$layoutId] = $setup['title'];
		}

		return $gridElements;
	}

	protected function getGridElementLayoutSetup() {
		if (!is_array($this->gridElementLayoutSetup)) {
			$layoutSetupObj = $this->getLayoutSetup();
			$layoutSetup = $layoutSetupObj->getLayoutSetup();

			if (is_array($layoutSetup) && count($layoutSetup)) {
				foreach ($layoutSetup as $layoutId => $setup) {
					if (isset($setup['title']) && (strpos($setup['title'], 'LLL:') !== FALSE)) {
						$layoutSetup[$layoutId]['title'] = $layoutSetupObj->getLanguageService()->sL($setup['title']);
					}
				}

				$this->gridElementLayoutSetup = $layoutSetup;
			} else {
				$this->gridElementLayoutSetup = array();
			}
		}

		return $this->gridElementLayoutSetup;
	}

	protected function getLayoutSetup() {
		if (!$this->layoutSetup) {
			/** @var \GridElementsTeam\Gridelements\Backend\LayoutSetup layoutSetup */
			$this->layoutSetup = GeneralUtility::makeInstance('GridElementsTeam\Gridelements\Backend\LayoutSetup');
			$this->layoutSetup->init($GLOBALS['_GET']['id'], array());
		}

		return $this->layoutSetup;
	}

	public function getGeContentCols($layoutId) {
		$contentColumns = array();

//		$layoutSetup = $this->getGridElementLayoutSetup();
		$layoutSetup = $this->getLayoutSetup();
		$columns = $layoutSetup->getLayoutColumnsSelectItems($layoutId);
		
		foreach ($columns as $column) {
			if (isset($column[0]) && isset($column[1])) {
				$contentColumns[$column[1]] = $column[0];
			}
		}

		return $contentColumns;
	}

	/**
	 * Returns an array with names of content columns for the given TypoScript
	 *
	 * @param string $typoScript
	 * @return array
	 */
	private function getContentColsFromTs($typoScript) {
		$parser = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\TypoScript\\Parser\\TypoScriptParser');
		$parser->parse($typoScript);
		$data = $parser->setup['backend_layout.'];

		$contentCols = array();
		$contentCols[''] = \TYPO3\CMS\Extbase\Utility\LocalizationUtility::translate('label_select', 'sf_tv2fluidge');
		if ($data) {
			foreach($data['rows.'] as $row) {
				foreach($row['columns.'] as $column) {
					$contentCols[$column['colPos']] = $column['name'];
				}
			}
		}
		return $contentCols;
	}

	/**
	 * Returns the tt_content record by uid
	 *
	 * @param int $uid
	 * @return mixed
	 */
	public function getContentElementByUid($uid) {
		$fields = '*';
		$table = 'tt_content';
		$where = 'uid='  . intval($uid);

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow($fields, $table, $where, '', '', '');

		return $res;
	}

	/**
	 * Returns all tt_content elements which contains a TemplaVoila FCE with the given uid
	 *
	 * @param int $uidFce
	 * @return mixed
	 */	
	 public function getContentElementsByFce($uidFce, $pageUids) {
		$fields = '*';
		$table = 'tt_content';
		$where = 'CType = "templavoilaplus_pi1" AND tx_templavoilaplus_to=' . intval($uidFce) .
		' AND pid IN (' . implode(',', $pageUids) . ')' .
			\TYPO3\CMS\Backend\Utility\BackendUtility::deleteClause('tt_content');
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($fields, $table, $where, '', '', '');

		return $res;
	}

	/**
	 * Migrated the content from a TemplaVoila FCE to the given Grid Element
	 *
	 * @param array $contentElement
	 * @param string|int $geKey
	 * @return void
	 */
	public function migrateFceFlexformContentToGe($contentElement, $geKey) {
		$tvTemplateUid = (int)$contentElement['tx_templavoilaplus_to'];
		$flexform = $this->sharedHelper->cleanFlexform($contentElement['tx_templavoilaplus_flex'], $tvTemplateUid, false);
		$GLOBALS['TYPO3_DB']->exec_UPDATEquery('tt_content', 'uid=' . intval($contentElement['uid']),
			array(
				'CType' => 'gridelements_pi1',
				'pi_flexform' => $flexform,
				'tx_gridelements_backend_layout' => $geKey
			)
		);
	}

	/**
	 * Marks the TemplaVoila FCE with the given uid as deleted
	 *
	 * @param int $uidFce
	 * @return void
	 */
	public function markFceDeleted($uidFce) {
		$GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_templavoilaplus_tmplobj', 'uid=' . intval($uidFce),
			array('deleted' => 1)
		);
	}

	/**
	 * Migrates all content elements for the FCE with the given uid to the selected column positions
	 *
	 * @param array $contentElement
	 * @param array $formdata
	 * @return int Number of Content elements updated
	 */
	public function migrateContentElementsForFce($contentElement, $formdata) {
		$fieldMapping = $this->sharedHelper->getFieldMappingArray($formdata, 'tv_col_', 'ge_col_');
		$tvContentArray = $this->sharedHelper->getTvContentArrayForContent($contentElement['uid']);
		$translationParentUid = (int)$contentElement['l18n_parent'];
		$sysLanguageUid = (int)$contentElement['sys_language_uid'];
		$pageUid = (int)$contentElement['pid'];

		$count = 0;
		$sorting = 0;
		  // Respect language
		        foreach ($tvContentArray as $lang => $fields) {

		            foreach ($fields as $key => $contentUidString) {
		                if (array_key_exists($key, $fieldMapping) && $contentUidString != '') {
		                    $contentUids = explode(',', $contentUidString);
		                    foreach ($contentUids as $contentUid) {
		                        $contentUid = (int)$contentUid;
		                        $myContentElement = NULL;
		                        $myContentElement = $this->sharedHelper->getContentElement($contentUid);
		                        $containerUid = (int)$contentElement['uid'];
		                        if (($translationParentUid > 0) && ($sysLanguageUid > 0)) {
		                            $myCeTranslationParentUid = (int)$myContentElement['uid'];
		                            if ($myCeTranslationParentUid > 0) {
		                                $tmpMyContentElement = $this->sharedHelper->getTranslationForContentElementAndLanguage($myCeTranslationParentUid, $sysLanguageUid);
		                                $tmpMyContentUid = (int)$tmpMyContentElement['uid'];
		                                if ($tmpMyContentUid > 0) {
		                                    $contentUid = $tmpMyContentUid;
		                                    $myContentElement = $tmpMyContentElement;
		                                } else {
		                                    $containerUid = $translationParentUid;
		                                }
		                            } else {
		                                $containerUid = $translationParentUid;
		                            }
		                        } else {
		                            $myContentElement = $this->sharedHelper->getContentElement($contentUid);
		                        }

		                        if (intval($myContentElement['pid']) === $pageUid) {
		                            $this->sharedHelper->updateContentElementForGe($contentUid, $containerUid, $fieldMapping[$key], $sorting);
		                        }
		                        $sorting += 25;
		                        $count++;

		                        $this->sharedHelper->fixContentElementLocalizationDiffSources($contentUid);
		                        $this->refIndex->updateRefIndexTable('tt_content', $contentUid);
		                    }
		                }
		            }
		        }

		return $count;
	}
}

?>

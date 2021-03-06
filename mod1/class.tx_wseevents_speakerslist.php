<?php
/***************************************************************
* Copyright notice
*
* (c) 2007-2009 Michael Oehlhof <typo3@oehlhof.de>
* All rights reserved
*
* This script is part of the TYPO3 project. The TYPO3 project is
* free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
*
* The GNU General Public License can be found at
* http://www.gnu.org/copyleft/gpl.html.
*
* This script is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/***************************************************************
*  Because I dont want to redefine the wheel again, some ideas
*  and code snippets are taken from the seminar manager extension
*  tx_seminars
***************************************************************/


/**
 * Class 'events list' for the 'wse_events' extension.
 *
 * @package		TYPO3
 * @subpackage	wse_events
 * @author		Michael Oehlhof <typo3@oehlhof.de>
 */
class tx_wseevents_speakerslist extends tx_wseevents_backendlist{

	/**
	 * The constructor. Calls the constructor of the parent class and sets
	 * $this->tableName.
	 *
	 * @param	object		$page the current back-end page object
	 * @return	void		...
	 */
	function tx_wseevents_speakerslist(&$page) {
		parent::tx_wseevents_backendlist($page);
		$this->tableName = $this->tableSpeakers;
		t3lib_div::loadTCA($this->tableName);
	}

	/**
	 * Generates and prints out an event list.
	 *
	 * @param	array		$table the table where the record data is to be addded
	 * @param	array		$row the current record
	 * @return	void
	 */
	function addRowToTable(&$table, $row) {
		global $BE_USER, $BACK_PATH;
		$uid = $row['uid'];
		$hidden = $row['hidden'];

		// Get language flag
		list($imglang, $imgtrans) = $this->makeLocalizationPanel($this->tableName,$row);

		// If deleted in the active workspace show the delete icon instead of the delete link
		if ('DELETED!' == $row['t3ver_label']) {
			$deleteIcon = '<img'
				. t3lib_iconWorks::skinImg(
					$BACK_PATH,
					'gfx/i/shadow_delete.png',
					'width="16" height="14"'
					)
				. '>';
		} else {
			$deleteIcon = $this->getDeleteIcon($uid);
		}
		
		// Add the result row to the table array.
		$table[] = array(
			TAB . TAB . TAB . TAB . TAB
				. t3lib_div::fixed_lgd_cs(
					$row['name'],
					$BE_USER->uc['titleLen']
				) . LF,
			TAB . TAB . TAB . TAB . TAB
				. t3lib_div::fixed_lgd_cs(
					$row['firstname'],
					$BE_USER->uc['titleLen']
				) . LF,
			TAB . TAB . TAB . TAB . TAB
				. $imglang . LF,
			TAB . TAB . TAB . TAB . TAB
				. $imgtrans . LF,
			TAB . TAB . TAB . TAB . TAB
				. $this->getEditIcon($uid) . LF
				. TAB . TAB . TAB . TAB . TAB
				. $deleteIcon . LF
				. TAB . TAB . TAB . TAB . TAB
				. $this->getHideUnhideIcon(
					$uid,
					$hidden
				) . LF,
		);
	}


	/**
	 * Generates and prints out an event list.
	 *
	 * @return	string		the HTML source code of the event list
	 * @access public
	 */
	function show() {
		global $TCA, $LANG;

		// Initialize the variable for the HTML source code.
		$content = '';

		// Set the table layout of the event list.
		$tableLayout = array(
			'table' => array(
				TAB . TAB . '<table cellpadding="0" cellspacing="0" class="typo3-dblist" border="1" rules="rows">' . LF,
				TAB . TAB . '</table>' . LF
			),
			array(
				'tr' => array(
					TAB . TAB . TAB . '<thead>' . LF
						. TAB . TAB . TAB . TAB . '<tr class="c-headLineTable">' . LF,
					TAB . TAB . TAB . TAB . '</tr>' . LF
						. TAB . TAB . TAB . '</thead>' . LF
				),
				'defCol' => array(
					TAB . TAB . TAB . TAB . TAB . '<td>' . LF,
					TAB . TAB . TAB . TAB . TAB . '</td>' . LF
				)
			),
			'defRow' => array(
				'tr' => array(
					TAB . TAB . TAB . '<tr>' . LF,
					TAB . TAB . TAB . '</tr>' . LF
				),
				array(
					TAB . TAB . TAB . TAB . '<td>' . LF,
					TAB . TAB . TAB . TAB . '</td>' . LF
				),
				array(
					TAB . TAB . TAB . TAB . '<td>' . LF,
					TAB . TAB . TAB . TAB . '</td>' . LF
				),
				array(
					TAB . TAB . TAB . TAB . '<td>' . LF,
					TAB . TAB . TAB . TAB . '</td>' . LF
				),
				'defCol' => array(
					TAB . TAB . TAB . TAB . '<td>' . LF,
					TAB . TAB . TAB . TAB . '</td>' . LF
				)
			)
		);

		// Fill the first row of the table array with the header.
		$table = array(
			array(
				TAB . TAB . TAB . TAB . TAB . TAB
					. '<span style="font-weight: bold;">'
					. $LANG->getLL('speakers.name') . '</span>' . LF,
				TAB . TAB . TAB . TAB . TAB . TAB
					. '<span style="font-weight: bold;">'
					. $LANG->getLL('speakers.firstname') . '</span>' . LF,
				TAB . TAB . TAB . TAB . TAB . TAB
					. '<span style="font-weight: bold;">'
					. $LANG->getLL('language') . '</span>' . LF,
				TAB . TAB . TAB . TAB . TAB . TAB
					. '<span style="font-weight: bold;">'
					. $LANG->getLL('translate') . '</span>' . LF,
				'',
			)
		);

		// Get date format for selected language
		if (!$this->conf[$GLOBALS['TSFE']->sys_language_uid . '.']['fmtDate']){
			$this->conf['strftime'] = '%d.%m.%Y';
		} else {
			$this->conf['strftime'] = $this->conf[$GLOBALS['TSFE']->sys_language_uid . '.']['fmtDate'];
		}

		// Initialize languages
		$this->initializeLanguages($this->page->pageInfo['uid']);

		// Get list of pid
		$this->selectedPids = $this->getRecursiveUidList($this->page->pageInfo['uid'],2);
		// Check if sub pages available and remove main page from list
		if ($this->selectedPids<>$this->page->pageInfo['uid']) {
			$this->selectedPids = t3lib_div::rmFromList($this->page->pageInfo['uid'],$this->selectedPids);
		}
		// Remove pages with eveent data
		$commonPids = $this->removeEventPages($this->selectedPids);
		// If all in one page than use page id
		if (empty($commonPids)) {
			$commonPids = $this->page->pageInfo['uid'];
		}
		// Get page titles
		$this->selectedPidsTitle = $this->getPidTitleList($this->selectedPids);
		// Get the where clause
		$wherePid = 'pid IN (' . $GLOBALS['TYPO3_DB']->cleanIntList($this->selectedPids) . ')';

		// Add icon for new record
		if (!empty($commonPids)) {
			$content .= $this->getNewIconList($commonPids,$this->selectedPidsTitle);
		}

		// Initialize variables for the database query.
		$queryWhere = '1=1' . t3lib_BEfunc::deleteClause($this->tableName)
			. ' AND ' . $TCA[$this->tableName]['ctrl']['languageField'] . '=0'
			. t3lib_BEfunc::versioningPlaceholderClause($this->tableName);
		$groupBy = '';
		$orderBy = 'name,firstname,sys_language_uid';
		$limit = '';

		// Get list of all events
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'*',
			$this->tableName,
			$queryWhere,
			$groupBy,
			$orderBy,
			$limit);

		if ($res) {
			$found = false;
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				// Overlaying record with workspace version if any
				t3lib_BEfunc::workspaceOL($this->tableName, $row);
				// Get the workspace version if available
				$newrow = t3lib_BEfunc::getWorkspaceVersionOfRecord($GLOBALS['BE_USER']->workspace, 
					$this->tableName, $row['uid']);
				// If no workspace version is available and original record is not active (pid=-1)
				// or original record is only a placeholder than use empty record (don't show record)
				if (!is_array($newrow) AND ((-1 == $row['pid']) OR ('INITIAL PLACEHOLDER' == $row['t3ver_label']))) {
					$row = $newrow;
				}
				// Get correct pid for the workspace record
				t3lib_BEfunc::fixVersioningPid($this->tableName, $row);
				if (is_array($row) 
					AND (t3lib_div::inList($GLOBALS['TYPO3_DB']->cleanIntList($this->selectedPids), $row['pid']))) {
					$found = true;
					$this->addRowToTable($table, $row);

					// Check for translations.
					$queryWhere = $wherePid . ' AND l18n_parent=' . $row['uid']
						. t3lib_BEfunc::deleteClause($this->tableName)
						. t3lib_BEfunc::versioningPlaceholderClause($this->tableName);
					$groupBy = '';
					$orderBy = $TCA[$this->tableName]['ctrl']['languageField'];
					$limit = '';

					// Get list of all translated sessions
					$reslang = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
						'*',
						$this->tableName,
						$queryWhere,
						$groupBy,
						$orderBy,
						$limit);
					if ($reslang) {
						while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($reslang)) {
							$this->addRowToTable($table, $row);
						}
						$GLOBALS['TYPO3_DB']->sql_free_result($reslang);
					}
				}
			}
			$GLOBALS['TYPO3_DB']->sql_free_result($res);
			if ($found) {
				// Output the table array using the tableLayout array with the template
				// class.
				$content .= $this->page->doc->table($table, $tableLayout) . '<br />' . LF;
			} else {
				$content .= '<br />' . $LANG->getLL('norecords') . '<br /><br />' . LF;
			}
		}
		return $content;
	}

	/**
	 * Generates a linked hide or unhide icon depending on the record's hidden
	 * status.
	 *
	 * @param	integer		$uid the UID of the record
	 * @param	boolean		$hidden indicates if the record is hidden (true) or is visible (false)
	 * @return	string		the HTML source code of the linked hide or unhide icon
	 * @access protected
	 */
	function getHideUnhideIcon($uid, $hidden) {
		global $BACK_PATH, $LANG, $BE_USER;
		$result = '';

		if ($BE_USER->check('tables_modify', $this->tableName)
			&& $BE_USER->doesUserHaveAccess(t3lib_BEfunc::getRecord('pages', $this->page->pageInfo['uid']), 16)) {
			if ($hidden) {
				$params = '&data[' . $this->tableName . '][' . $uid . '][hidden]=0';
				$icon = 'gfx/button_unhide.gif';
				$langHide = $LANG->getLL('unHide');
			} else {
				$params = '&data[' . $this->tableName . '][' . $uid . '][hidden]=1';
				$icon = 'gfx/button_hide.gif';
				$langHide = $LANG->getLL('hide');
			}

			$result = '<a href="'
				. htmlspecialchars($this->page->doc->issueCommand($params)) . '">'
				. '<img'
				. t3lib_iconWorks::skinImg(
					$BACK_PATH,
					$icon,
					'width="11" height="12"'
				)
				. ' title="' . $langHide . '" alt="' . $langHide . '" class="hideicon" />'
				. '</a>';
		}

		return $result;
	}

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/wse_events/mod1/class.tx_wseevents_speakerslist.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/wse_events/mod1/class.tx_wseevents_speakerslist.php']);
}

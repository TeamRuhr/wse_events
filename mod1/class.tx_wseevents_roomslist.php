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
 * Class 'tx_wseevents_roomslist' for the 'wse_events' extension.
 *
 * @package		TYPO3
 * @subpackage	wse_events
 * @author		Michael Oehlhof <typo3@oehlhof.de>
 */
class tx_wseevents_roomslist extends tx_wseevents_backendlist{

	/**
	 * The constructor. Calls the constructor of the parent class and sets
	 * $this->tableName.
	 *
	 * @param	object		$page the current back-end page object
	 * @return	void		...
	 */
	function tx_wseevents_roomslist(&$page) {
		parent::tx_wseevents_backendlist($page);
		$this->tableName = $this->tableRooms;
#		$this->page = $page;
	}

	/**
	 * Generates and prints out an event list.
	 *
	 * @return	string		the HTML source code of the event list
	 * @access public
	 */
	function show() {
		global $TCA, $LANG, $BE_USER;

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
		$tableheader = array(
			array(
				TAB . TAB . TAB . TAB . TAB . TAB
					. '<span style="font-weight: bold;">'
					. $LANG->getLL('rooms.name') . '</span>' . LF,
				TAB . TAB . TAB . TAB . TAB . TAB
					. '<span style="font-weight: bold;">'
					. $LANG->getLL('rooms.seats') . '</span>' . LF,
				TAB . TAB . TAB . TAB . TAB . TAB
					. '<span style="font-weight: bold;">'
					. $LANG->getLL('rooms.number') . '</span>' . LF,
				'',
			)
		);

		// Get date format for selected language
		if (!$this->conf[$GLOBALS['TSFE']->sys_language_uid . '.']['fmtDate']){
			$this->conf['strftime'] = '%d.%m.%Y';
		} else {
			$this->conf['strftime'] = $this->conf[$GLOBALS['TSFE']->sys_language_uid . '.']['fmtDate'];
		}

		// Get list of pid
		$this->selectedPids = $this->getRecursiveUidList($this->page->pageInfo['uid'],2);
		// Check if sub pages available and remove main page from list
		if ($this->selectedPids<>$this->page->pageInfo['uid']) {
			$this->selectedPids = t3lib_div::rmFromList($this->page->pageInfo['uid'],$this->selectedPids);
		}
		// Remove pages with event data
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

		// -------------------- Get list of locations --------------------
		// Initialize variables for the database query.
		$queryWhere = $wherePid . t3lib_BEfunc::deleteClause($this->tableLocations)
			. ' AND ' . $TCA[$this->tableName]['ctrl']['languageField'] . '=0'
			. t3lib_BEfunc::versioningPlaceholderClause($this->tableName);
		$groupBy = '';
		$orderBy = 'name';
		$limit = '';

		// Get list of all events
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'*',
			$this->tableLocations,
			$queryWhere,
			$groupBy,
			$orderBy,
			$limit);

		$locations = array();
		if ($res) {
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$location = array();
				$location['uid'] = $row['uid'];
				$location['name'] = $row['name'];
				$locations[] = $location;
			}
			$GLOBALS['TYPO3_DB']->sql_free_result($res);
		}

		// Add box for location selection


		// Get list of rooms for an location
		foreach ($locations as $location) {
			// Show name of location
			$content .= '<span style="font-size:1.2em"><b>' . $LANG->getLL('rooms.location') . ' ' . $location['name'] . '</b></span>';

			// Initialize variables for the database query.
			$queryWhere = $wherePid . ' AND location='.$location['uid']
				. t3lib_BEfunc::deleteClause($this->tableName)
				. ' AND ' . $TCA[$this->tableName]['ctrl']['languageField'] . '=0'
				. t3lib_BEfunc::versioningPlaceholderClause($this->tableName);
			$groupBy = '';
			$orderBy = 'number';
			$limit = '';

			// Get list of all time slots
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'*',
				$this->tableName,
				$queryWhere,
				$groupBy,
				$orderBy,
				$limit);

			// Clear output table
			$table = $tableheader;

			if ($res) {
				$found = false;
				while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
					$found = true;
					$uid = $row['uid'];
					$hidden = $row['hidden'];
					// Add the result row to the table array.
					$table[] = array(
						TAB . TAB . TAB . TAB . TAB
							. t3lib_div::fixed_lgd_cs(
								$row['name'],
								$BE_USER->uc['titleLen']
							) . LF,
						TAB . TAB . TAB . TAB . TAB
							. $row['seats'] . LF,
						TAB . TAB . TAB . TAB . TAB
							. $row['number'] . LF,
						TAB . TAB . TAB . TAB . TAB
							. $this->getEditIcon($uid).LF
							. TAB . TAB . TAB . TAB . TAB
							. $this->getDeleteIcon($uid) . LF
							. TAB . TAB . TAB . TAB . TAB
							. $this->getHideUnhideIcon(
								$uid,
								$hidden
							) . LF,
					);
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
		}

		return $content;
	}

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/wse_events/mod1/class.tx_wseevents_roomslist.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/wse_events/mod1/class.tx_wseevents_roomslist.php']);
}

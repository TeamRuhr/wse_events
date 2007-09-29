<?php
/***************************************************************
* Copyright notice
*
* (c) 2007 Michael Oehlhof
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


/**
 * Class 'tx_wseevents_rooms' for the 'wse_events' extension.
 *
 * xxxxxxx
 * xxxxx
 *
 *
 * @package		TYPO3
 * @subpackage	tx_wseevents
 * @author		Michael Oehlhof
 */

// In case we're on the back end, PATH_tslib isn't defined yet.
if (!defined('PATH_tslib')) {
	define('PATH_tslib', t3lib_extMgm::extPath('cms').'tslib/');
}

// If we are in the back end, we include the extension's locallang.xml.
if ((TYPO3_MODE == 'BE') && is_object($LANG)) {
    $LANG->includeLLFile('EXT:wse_events/mod1/locallang.xml');
}

	/**
	 * [Describe function...]
	 *
	 */
class tx_wseevents_rooms {
	/** The extension key. */
	var $extKey = 'wseevents';

	/**
	 * Dummy constructor: Does nothing.
	 *
	 * The base classe's constructor is called in $this->init().
	 *
	 * @return	[type]		...
	 */
	function tx_wseevents_rooms() {
	}

	/**
	 *
	 * @param	array		TypoScript configuration for the plugin
	 * @return	[type]		...
	 * @access protected
	 */
	function main($items) {
		return;
	}

	/**
	 *
	 * @param	array		TypoScript configuration for the plugin
	 * @return	[type]		...
	 * @access protected
	 */
	function getTCAroomlist($PA,$fobj) {
#		debug ($PA);
#		debug ($fobj);

		// --------------------- Get the location of the selected event ---------------------
		// Initialize variables for the database query.
		$tableName ='tx_wseevents_events';
		$queryWhere = 'uid='.$PA['row']['event'].' AND deleted=0 AND sys_language_uid=0';
		$additionalTables = '';
		$groupBy = '';
		$orderBy = 'name';
		$limit = '';

		// Check if event is selected, if not get first event 
		if ($PA['row']['event'] == 0) {
			$queryWhere = 'pid='.$PA['row']['pid'].' AND deleted=0 AND sys_language_uid=0';
		}

		// Get location of the event
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'*',
			$tableName,
			$queryWhere,
			$groupBy,
			$orderBy,
			$limit);
		
		$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)		;
		$location = $row['location'];
		
		// --------------------- Get the rooms of the location of the selected event ---------------------
		// Initialize variables for the database query.
		$tableName ='tx_wseevents_rooms';
		$queryWhere = 'location='.$location.' AND deleted=0 AND sys_language_uid=0';
		$additionalTables = '';
		$groupBy = '';
		$orderBy = 'name';
		$limit = '';

		// Get list of all rooms of the location
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'*',
			$tableName,
			$queryWhere,
			$groupBy,
			$orderBy,
			$limit);

		// check if selected room is in location
		$roomfound = false;
		
		// Clear the item array
		$PA['items'] = array();
		// Fill item array with rooms of location of selected event
		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			// Add the name and id to the itemlist
			$entry = array();
			$entry[0] = $row['name'];
			$entry[1] = $row['uid'];
			$entry[2] = '';
			$PA['items'][] = $entry;
			if ($row['uid'] = $PA['row']['room']) {
				$roomfound = true;
			}
		}
		
		// Set selected room to first room of location, if given room is from another location
		if (!$roomfound) {
			$PA['row']['room'] = $PA['items']['0']['1'];
		}
		
#		debug ($PA);

		return;
	}

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/wse_events/class.tx_wseevents_rooms.php']) {
	include_once ($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/wse_events/class.tx_wseevents_rooms.php']);
}

?>

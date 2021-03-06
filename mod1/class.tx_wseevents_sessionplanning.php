<?php
/***************************************************************
* Copyright notice
*
* (c) 2007 Michael Oehlhof <typo3@oehlhof.de>
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
 * Class 'tx_wseevents_sessionplanning' for the 'wse_events' extension.
 *
 * @package		TYPO3
 * @subpackage	wse_events
 * @author		Michael Oehlhof <typo3@oehlhof.de>
 */
class tx_wseevents_sessionplanning extends tx_wseevents_backendlist{

	/**
	 * The constructor. Calls the constructor of the parent class and sets
	 * $this->tableName.
	 *
	 * @param	object		$page the current back-end page object
	 * @return	void		...
	 */
	function tx_wseevents_timeslotslist(&$page) {
		parent::tx_wseevents_backendlist($page);
		$this->tableName = $this->tableTimeslots;
	}

	/**
	 * Generates and prints out a session plan.
	 *
	 * @return	string		the HTML source code of the session planning
	 * @access public
	 */
	function show() {
		// Define initial comment
		$initComment = LF . TAB . '<!-- WSE_EVENTS session planning -->' . LF;

		// Initialize the variable for the HTML source code.
		$content = $initComment;

		// Get date format for selected language
		if (!$this->conf[$GLOBALS['TSFE']->sys_language_uid . '.']['fmtDate']){
			$this->conf['strftime'] = '%d.%m.%Y';
		} else {
			$this->conf['strftime'] = $this->conf[$GLOBALS['TSFE']->sys_language_uid . '.']['fmtDate'];
		}

		$content .= '<div align=center><strong>Working on implementation yet ...</strong></div>';

		return $content;
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/wse_events/mod1/class.tx_wseevents_sessionplanning.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/wse_events/mod1/class.tx_wseevents_sessionplanning.php']);
}

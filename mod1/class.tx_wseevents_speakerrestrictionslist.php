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
 * Class 'tx_wseevents_speakerrestrictionslist' for the 'wse_events' extension.
 *
 * @package		TYPO3
 * @subpackage	wse_events
 * @author		Michael Oehlhof <typo3@oehlhof.de>
 */

require_once('conf.php');
require_once($BACK_PATH.'init.php');
require_once($BACK_PATH.'template.php');
require_once(t3lib_extMgm::extPath('wse_events').'mod1/class.tx_wseevents_backendlist.php');
require_once(t3lib_extMgm::extPath('wse_events').'class.tx_wseevents_events.php');


class tx_wseevents_speakerrestrictionslist extends tx_wseevents_backendlist{

	/**
	 * The constructor. Calls the constructor of the parent class and sets
	 * $this->tableName.
	 *
	 * @param	object		the current back-end page object
	 * @return	[type]		...
	 */
	function tx_wseevents_speakerrestrictionslist(&$page) {
		parent::tx_wseevents_backendlist($page);
		$this->tableName = $this->tableSpeakerRestrictions;
#		$this->page = $page;
	}

	/**
	 * Generates and prints out an event list.
	 *
	 * @return	string		the HTML source code of the event list
	 * @access public
	 */
	function show() {
		global $LANG, $BE_USER;

#debug ($LANG);
#debug ($BE_USER);

		// Get selected backend language of user
		$userlang = $BE_USER->uc[moduleData][web_layout][language];

		// Initialize the variable for the HTML source code.
		$content = '';

		$content .= $this->getNewIcon($this->page->pageInfo['uid']);

		// Set the table layout of the speaker restrictions list.
		$tableLayout = array(
			'table' => array(
				TAB.TAB.'<table cellpadding="0" cellspacing="0" class="typo3-dblist">'.LF,
				TAB.TAB.'</table>'.LF
			),
			array(
				'tr' => array(
					TAB.TAB.TAB.'<thead>'.LF
						.TAB.TAB.TAB.TAB.'<tr>'.LF,
					TAB.TAB.TAB.TAB.'</tr>'.LF
						.TAB.TAB.TAB.'</thead>'.LF
				),
				'defCol' => array(
					TAB.TAB.TAB.TAB.TAB.'<td class="c-headLineTable">'.LF,
					TAB.TAB.TAB.TAB.TAB.'</td>'.LF
				)
			),
			'defRow' => array(
				'tr' => array(
					TAB.TAB.TAB.'<tr>'.LF,
					TAB.TAB.TAB.'</tr>'.LF
				),
				array(
					TAB.TAB.TAB.TAB.'<td>'.LF,
					TAB.TAB.TAB.TAB.'</td>'.LF
				),
				array(
					TAB.TAB.TAB.TAB.'<td>'.LF,
					TAB.TAB.TAB.TAB.'</td>'.LF
				),
				array(
					TAB.TAB.TAB.TAB.'<td>'.LF,
					TAB.TAB.TAB.TAB.'</td>'.LF
				),
				'defCol' => array(
					TAB.TAB.TAB.TAB.'<td>'.LF,
					TAB.TAB.TAB.TAB.'</td>'.LF
				)
			)
		);

		// Fill the first row of the table array with the header.
		$table = array(
			array(
				TAB.TAB.TAB.TAB.TAB.TAB
					.'<span style="color: #ffffff; font-weight: bold;">'
					.$LANG->getLL('speakers.speaker').'</span>'.LF,
				TAB.TAB.TAB.TAB.TAB.TAB
					.'<span style="color: #ffffff; font-weight: bold;">'
					.$LANG->getLL('speakers.eventday').'</span>'.LF,
				TAB.TAB.TAB.TAB.TAB.TAB
					.'<span style="color: #ffffff; font-weight: bold;">'
					.$LANG->getLL('speakers.begin').'</span>'.LF,
				TAB.TAB.TAB.TAB.TAB.TAB
					.'<span style="color: #ffffff; font-weight: bold;">'
					.$LANG->getLL('speakers.end').'</span>'.LF,
				'',
			)
		);

		// unserialize the configuration array
		$globalConfiguration = unserialize(
			$GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['wse_events']
		);

		# Get date format for selected language
		if (!$conf[$index.'.']['fmtDate']){
			$conf['strftime'] = '%d.%m.%Y';
		} else {
			$conf['strftime'] = $conf[$index.'.']['fmtDate'];
		}

		// -------------------- Get list of speakers --------------------
		// Initialize variables for the database query.
		$queryWhere = 'deleted=0 AND sys_language_uid=0';
		$additionalTables = '';
		$groupBy = '';
		$orderBy = 'uid';
		$limit = '';

		// Get list of all events
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'*',
			$this->tableSpeakers,
			$queryWhere,
			$groupBy,
			$orderBy,
			$limit);

		$speakers = array();
		if ($res) {
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$speakers[$row['uid']] = $row['name'].', '.$row['firstname'];
			}
		}
		
		// -------------------- Get list of events --------------------
		// Initialize variables for the database query.
		$queryWhere = 'pid='.$this->page->pageInfo['uid'].' AND deleted=0 AND sys_language_uid=0';
		$additionalTables = '';
		$groupBy = '';
		$orderBy = 'name';
		$limit = '';

		// Get list of all events
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'*',
			$this->tableEvents,
			$queryWhere,
			$groupBy,
			$orderBy,
			$limit);

		$events = array();
		if ($res) {
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$event = array();
				$event['uid'] = $row['uid'];
				$event['name'] = $row['name'];
				$events[] = $event;
			}
		}
		
		// Add box for event selection
		
		
		// Get list of sessions for an event
		foreach ($events as $event) {
			// Show name of event
			$content .= '<b>'.$event['name'].'</b><br />';

			// Get list of timeslots for the event
			$slots = tx_wseevents_events::getEventSlotlist($event['uid']);

			// Initialize variables for the database query.
			$queryWhere = 'pid='.$this->page->pageInfo['uid'].' AND event='.$event['uid'].' AND deleted=0';
			$additionalTables = '';
			$groupBy = '';
			$orderBy = 'speaker,eventday';
			$limit = '';

			// Get list of all speaker restrictions
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
					$found = true;
					$uid = $row['uid'];
					$hidden = $row['hidden'];
					// Add the result row to the table array.
					$table[] = array(
						TAB.TAB.TAB.TAB.TAB
							.$speakers[$row['speaker']].LF,
						TAB.TAB.TAB.TAB.TAB
							.$row['eventday'].LF,
						TAB.TAB.TAB.TAB.TAB
							.$slots[$row['begin']].LF,
						TAB.TAB.TAB.TAB.TAB
							.$slots[$row['end']].LF,
						TAB.TAB.TAB.TAB.TAB
							.$this->getEditIcon($uid).LF
							.TAB.TAB.TAB.TAB.TAB
							.$this->getDeleteIcon($uid).LF
							.TAB.TAB.TAB.TAB.TAB
							.$this->getHideUnhideIcon(
								$uid,
								$hidden
							).LF,
					);
				}
				if ($found) {
					// Output the table array using the tableLayout array with the template
					// class.
					$content .= $this->page->doc->table($table, $tableLayout);
				} else {
					$content .= $LANG->getLL('norecords').'<br /><br />'.LF;
				}
			}
		}


		return $content;
	}

	/**
	 * Generates a linked hide or unhide icon depending on the record's hidden
	 * status.
	 *
	 * @param	string		the name of the table where the record is in
	 * @param	integer		the UID of the record
	 * @param	boolean		indicates if the record is hidden (true) or is visible (false)
	 * @return	string		the HTML source code of the linked hide or unhide icon
	 * @access protected
	 */
	function getHideUnhideIcon($uid, $hidden) {
		global $BACK_PATH, $LANG, $BE_USER;
		$result = '';

		if ($BE_USER->check('tables_modify', $this->tableName)
			&& $BE_USER->doesUserHaveAccess(t3lib_BEfunc::getRecord('pages', $this->page->pageInfo['uid']), 16)) {
			if ($hidden) {
				$params = '&data['.$this->tableName.']['.$uid.'][hidden]=0';
				$icon = 'gfx/button_unhide.gif';
				$langHide = $LANG->getLL('unHide');
			} else {
				$params = '&data['.$this->tableName.']['.$uid.'][hidden]=1';
				$icon = 'gfx/button_hide.gif';
				$langHide = $LANG->getLL('hide');
			}

			$result = '<a href="'
				.htmlspecialchars($this->page->doc->issueCommand($params)).'">'
				.'<img'
				.t3lib_iconWorks::skinImg(
					$BACK_PATH,
					$icon,
					'width="11" height="12"'
				)
				.' title="'.$langHide.'" alt="'.$langHide.'" class="hideicon" />'
				.'</a>';
		}

		return $result;
	}

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/wse_events/mod1/class.tx_wseevents_speakerrestrictionslist.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/wse_events/mod1/class.tx_wseevents_speakerrestrictionslist.php']);
}

?>
<?php
/***************************************************************
* Copyright notice
*
* (c) 2007 Niels Pardon (mail@niels-pardon.de)
* All rights reserved
*
* Adapted for use by the 'wse_events' extension
* 2007-2009 by Michael Oehlhof <typo3@oehlhof.de>
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

define('TAB', chr(9));
define('LF', chr(10));

/**
 * Class 'tx_wseevents_backendlist' for the 'wse_events' extension.
 * Adapted from subpackage 'tx_seminars' for use by the 'wse_events' extension by Michael Oehlhof <typo3@oehlhof.de>
 *
 * @package		TYPO3
 * @subpackage	wse_events
 * @author		Niels Pardon <mail@niels-pardon.de>
 */
class tx_wseevents_backendlist extends tx_wseevents_dbplugin {
	/** the table we're working on */
	var $tableName;

	/** Holds a reference to the back-end page object. */
	var $page;

	/** Holds a list of pids of the sub pages of the selected page. */
	var $selectedPids;
	var $selectedPidsAll;
	var $selectedPidsTitle;

	// List of languages
	var $syslang;

	// Configuration
	var $conf;
	
	// Workspace
	var $workspaceActive;

	/**
	 * The constructor. Sets the table name and the back-end page object.
	 * Loads an array with system languages.
	 *
	 * @param	object		$page the current back-end page object
	 * @return	void		...
	 * @access public
	 */
	function tx_wseevents_backendlist(&$page) {
		global $BE_USER;

		$this->setTableNames();
		$this->page =& $page;
		
		// Set flag if workspace is active
		$this->workspaceActive = (0 <> $BE_USER->workspace);

		// Get array with system languges
		$this->syslang = t3lib_BEfunc::getSystemLanguages();
		foreach ($this->syslang as &$thislang) {
			$langname = explode(' ', $thislang[0]);
			$thislang[0] = $langname[0];
		}
	}


	/**
	 * Generates a list of titles of all pages for the given pid list.
	 *
	 * @param	string		$pidList the list of pids
	 * @return	array		the list of page titles
	 * @access public
	 */
	function getPidTitleList($pidList) {
		$titles = array();
		foreach (explode(',', $pidList) as $thisPid) {
			$row = t3lib_BEfunc::getRecord ('pages', $thisPid);
			$titles[$thisPid] = $row['title'];
		}
		return $titles;
	}

	/**
	 * Generates an edit record icon which is linked to the edit view of
	 * a record.
	 *
	 * @param	integer		$uid the uid of the record
	 * @return	string		the HTML source code to return
	 * @access public
	 */
	function getEditIcon($uid) {
		global $BACK_PATH, $LANG, $BE_USER;

		$result = '';

		// No edit icon if working in workspace
		if ((true == $this->workspaceActive) 
			AND (!t3lib_div::inList('tx_wseevents_speakers,tx_wseevents_sessions', $this->tableName))) {
			return $result;
		}
		
		if ($BE_USER->check('tables_modify', $this->tableName)
			&& $BE_USER->doesUserHaveAccess(t3lib_BEfunc::getRecord('pages', $this->page->pageInfo['uid']), 16)) {
			$params = '&edit[' . $this->tableName . '][' . $uid . ']=edit';
			$editOnClick = $this->editNewUrl($params, $BACK_PATH);
			$langEdit = $LANG->getLL('edit');
			$result = '<a href="' . htmlspecialchars($editOnClick) . '">'
				. '<img '
				. t3lib_iconWorks::skinImg(
					$BACK_PATH,
					'gfx/edit2.gif',
					'width="11" height="12"')
				. ' title="' . $langEdit . '" alt="' . $langEdit . '" class="icon" />'
				. '</a>';
		}

		return $result;
	}

	/**
	 * Generates a linked delete record icon whith a JavaScript confirmation
	 * window.
	 *
	 * @param	integer		$uid the uid of the record
	 * @return	string		the HTML source code to return
	 * @access public
	 */
	function getDeleteIcon($uid) {
		global $BACK_PATH, $LANG, $BE_USER;

		$result = '';

		// No delete icon if working in workspace
		if ((true == $this->workspaceActive) 
			AND (!t3lib_div::inList('tx_wseevents_speakers,tx_wseevents_sessions', $this->tableName))) {
			return $result;
		}

		if ($BE_USER->check('tables_modify', $this->tableName)
			&& $BE_USER->doesUserHaveAccess(t3lib_BEfunc::getRecord('pages', $this->page->pageInfo['uid']), 16)) {
			$params = '&cmd[' . $this->tableName . '][' . $uid . '][delete]=1';

			$referenceWarning = '';
			if ((float) $GLOBALS['TYPO3_CONF_VARS']['SYS']['compat_version'] >= 4.0) {
				$referenceWarning = t3lib_BEfunc::referenceCount(
					$this->tableName,
					$uid,
					' ' . $LANG->getLL('referencesWarning'));
			}

			$confirmation = htmlspecialchars(
				'if (confirm('
				. $LANG->JScharCode(
					$LANG->getLL('deleteWarning')
					.$referenceWarning)
				. ')) {return true;} else {return false;}');
			$langDelete = $LANG->getLL('delete', 1);
			$result = '<a href="'
				. htmlspecialchars($this->page->doc->issueCommand($params))
				. '" onclick="' . $confirmation . '">'
				. '<img'
				. t3lib_iconWorks::skinImg(
					$BACK_PATH,
					'gfx/garbage.gif',
					'width="11" height="12"'
				)
				. ' title="' . $langDelete . '" alt="' . $langDelete . '" class="deleteicon" />'
				. '</a>';
		}

		return $result;
	}

	/**
	 * Returns a "create new record" image tag that is linked to the new record view.
	 *
	 * @param	integer		$pid the page id where the record should be stored
	 * @param	integer		$useDiv flag to choose div tags (1) instead of span tags (<> 1)
	 * @return	string		the HTML source code to return
	 * @access public
	 */
	function getNewIcon($pid, $useDiv = 1) {
		global $BACK_PATH, $LANG, $BE_USER;

		// No new icon if working in workspace
		if ((true == $this->workspaceActive) 
			AND (!t3lib_div::inList('tx_wseevents_speakers,tx_wseevents_sessions', $this->tableName))) {
			return '';
		}
		$result = '';

		// the name of the table where the record should be saved to is stored in $this->tableName
		if ($BE_USER->check('tables_modify', $this->tableName)
			&& $BE_USER->doesUserHaveAccess(t3lib_BEfunc::getRecord('pages', $this->page->pageInfo['uid']), 16)
			&& $this->page->pageInfo['doktype'] == 254) {
			$params = '&edit[' . $this->tableName . '][' . $pid . ']=new';
			$editOnClick = $this->editNewUrl($params, $BACK_PATH);
			$langNew = $LANG->getLL('newRecordGeneral');
			if (1 == $useDiv) {
				$div = 'div';
			} else {
				$div = 'span';
			}
			$result = TAB . TAB
				. '<' . $div . ' id="typo3-newRecordLink">' . LF
				. TAB . TAB . TAB
				. '<a href="' . htmlspecialchars($editOnClick) . '">' . LF
				. TAB . TAB . TAB . TAB
				. '<img'
				. t3lib_iconWorks::skinImg(
					$BACK_PATH,
					'gfx/new_record.gif',
					'width="7" height="4"')
				// We use an empty alt attribute as we already have a textual
				// representation directly next to the icon.
				. ' title="' . $langNew . '" alt="" />' . LF
				. TAB . TAB . TAB . TAB
				. $langNew . LF
				. TAB . TAB . TAB
				. '</a>' . LF
				. TAB . TAB
				. '</' . $div . '>' . LF;
		}
		return $result;
	}


	/**
	 * Returns a list of "create new record" image tags that are linked to the new record view.
	 *
	 * @param	string		$pidList the list with page ids where the record should be stored
	 * @param	array		$pidTitles array with page titles for all pages in $pidList
	 * @return	string		the HTML source code to return
	 * @access public
	 */
	function getNewIconList($pidList, $pidTitles) {
		global $BACK_PATH, $LANG, $BE_USER;

		// No new icon if working in workspace
		if ((true == $this->workspaceActive) 
			AND (!t3lib_div::inList('tx_wseevents_speakers,tx_wseevents_sessions', $this->tableName))) {
			$result = TAB . '<br /><b>' . $LANG->getLL('onlylivews') . '</b><br /><br />' . LF;
			return $result;
		}

		$result = TAB . '<br /><b>' . $LANG->getLL('newrecord') . '</b>&nbsp;' . LF;
		$result .= TAB . '<div id="typo3-newRecordLink">' . LF;
		foreach (explode(',', $pidList) as $thisPid) {
			// the name of the table where the record should be saved to is stored in $this->tableName
			if ($BE_USER->check('tables_modify', $this->tableName)
				&& $BE_USER->doesUserHaveAccess(t3lib_BEfunc::getRecord('pages', $this->page->pageInfo['uid']), 16)
				&& $this->page->pageInfo['doktype'] == 254) {
				$params = '&edit[' . $this->tableName . '][' . $thisPid . ']=new';
				$editOnClick = $this->editNewUrl($params, $BACK_PATH);
				$result .= TAB . TAB . '<a href="' . htmlspecialchars($editOnClick) . '">' . LF
					. TAB . TAB . TAB . '<img' . t3lib_iconWorks::skinImg(
						$BACK_PATH,
						'gfx/new_record.gif',
						'width="7" height="4"')
					// We use an empty alt attribute as we already have a textual
					// representation directly next to the icon.
					. ' title="' . $pidTitles[$thisPid] . '" alt="" />' . LF
					. TAB . TAB . TAB . $pidTitles[$thisPid] . LF
					. TAB . TAB . '</a>' . LF
					. TAB . TAB . '<br />' . LF;
			}
		}
		$result .= TAB . '</div><br />' . LF;

		return $result;
	}


	/**
	 * Returns the url for the "create new record" link and the "edit record" link.
	 *
	 * @param	string		$params the parameters for tce
	 * @param	string		$backPath the back-path to the /typo3 directory
	 * @return	string		the url to return
	 * @access protected
	 */
	function editNewUrl($params, $backPath = '') {
		// No new icon if working in workspace
		if ((true == $this->workspaceActive) 
			AND (!t3lib_div::inList('tx_wseevents_speakers,tx_wseevents_sessions', $this->tableName))) {
			return '';
		}

		$returnUrl = 'returnUrl=' . rawurlencode(t3lib_div::getIndpEnv('REQUEST_URI'));

		return $backPath . 'alt_doc.php?' . $returnUrl . $params;
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

		// No hide/unhide icon if working in workspace
		if ((true == $this->workspaceActive) 
			AND (!t3lib_div::inList('tx_wseevents_speakers,tx_wseevents_sessions', $this->tableName))) {
			return '';
		}

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

	/**
	 * Creates the localization panel
	 *
	 * @param	string		$table The table
	 * @param	array		$row The record for which to make the localization panel.
	 * @return	array		Array with key 0/1 with content for column 1 and 2
	 */
	function makeLocalizationPanel($table, $row) {
		// No hide/unhide icon if working in workspace
		if ((true == $this->workspaceActive) 
			AND (!t3lib_div::inList('tx_wseevents_speakers,tx_wseevents_sessions', $this->tableName))) {
			$out = array(
				0 => '',
				1 => '',
			);
			return $out;
		}
		return tx_wseevents_dbplugin::makeLocalizationPanel($table, $row);
	}
	
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/wse_events/mod1/class.tx_wseevents_backendlist.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/wse_events/mod1/class.tx_wseevents_backendlist.php']);
}

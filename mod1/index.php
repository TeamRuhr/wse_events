<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007-2009 Michael Oehlhof (typo3@oehlhof.de)
*  All rights reserved
*
*  Because I dont want to redefine the wheel again, some ideas
*  and code snippets are taken from the seminar manager extension
*  tx_seminars
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


// initialization of the module
unset($MCONF);

require('conf.php');
require($BACK_PATH . 'init.php');
require($BACK_PATH . 'template.php');

$GLOBALS['LANG']->includeLLFile('EXT:lang/locallang_show_rechis.xml');
$GLOBALS['LANG']->includeLLFile('EXT:lang/locallang_mod_web_list.xml');
$GLOBALS['LANG']->includeLLFile('EXT:wse_events/mod1/locallang.xml');

require_once (PATH_t3lib . 'class.t3lib_scbase.php');

// This checks permissions and exits if the users has no permission for entry.
$GLOBALS['BE_USER']->modAccess($MCONF, 1);



/**
 * Module 'WSE Events' for the 'wse_events' extension.
 *
 * @author	 	Michael Oehlhof
 * @package		TYPO3
 * @subpackage	wse_events
 */
class tx_wseevents_module1 extends t3lib_SCbase {
	var $pageInfo;

	/** an array of available sub modules */
	var $availableSubModules;

	/** the currently selected sub module */
	var $subModule;

	/** Variable for session data */
	var $my_vars;
	
	/** Variable for selected menu function */
	var $selectedFunction;

	/**
	 * Initializes the Module
	 *
	 * @return	void
	 */
	function init()	{
#		global $BE_USER, $LANG, $BACK_PATH, $TCA_DESCR, $TCA, $CLIENT, $TYPO3_CONF_VARS;

		/*
		 * This is a workaround for the wrong generated links. The workaround is needed to
		 * get the right values from the GET Parameter. This workaround is from Elmar Hinz
		 * who also noted this in the bug tracker (http://bugs.typo3.org/view.php?id=2178).
		 */
		$matches = array();
		foreach ($GLOBALS['_GET'] as $key => $value) {
			if (preg_match('/amp;(.*)/', $key, $matches)) {
				$GLOBALS['_GET'][$matches[1]] = $value;
			}
		}
		/* --- END OF Workaround --- */

		parent::init();

		$this->id = intval($this->id);
	}

	/**
	 * Adds items to the ->MOD_MENU array. Used for the function menu selector.
	 *
	 * @return	void
	 */
	function menuConfig()	{
		global $LANG;
		$this->MOD_MENU = Array (
			'function' => Array (
				'1' => $LANG->getLL('function1'),  // Event data
				'2' => $LANG->getLL('function2'),  // Common data
				'3' => $LANG->getLL('function3'),  // Session planning
			)
		);
		parent::menuConfig();
	}

	/**
	 * Main function of the module. Write the content to $this->content
	 * If you chose "web" as main module, you will need to consider the $this->id parameter which will contain the uid-number of the page clicked in the page tree
	 *
	 * @return	void		...
	 */
	function main()	{
		global $BE_USER, $LANG, $BACK_PATH; //, $TCA_DESCR, $TCA, $CLIENT, $TYPO3_CONF_VARS;

		// Access check!
		// The page will show only if there is a valid page and if this page may be viewed by the user
		$this->pageInfo = t3lib_BEfunc::readPageAccess($this->id, $this->perms_clause);
		$access = is_array($this->pageInfo) ? 1 : 0;

		if (($this->id && $access) || ($BE_USER->user['admin'] && !$this->id) && ($this->id>0))	{

			// Get session data
			$this->my_vars = $GLOBALS["BE_USER"]->getSessionData("tx_wseevents");

			// Draw the header.
			$this->doc = t3lib_div::makeInstance('bigDoc');
			$this->doc->backPath = $BACK_PATH;
			$this->doc->form='<form action="" method="POST">';

			// JavaScript
			$this->doc->JScode = '
				<script language="javascript" type="text/javascript">
					script_ended = 0;
					function jumpToUrl(URL)	{
						document.location = URL;
					}
				</script>
			';
			$this->doc->postCode='
				<script language="javascript" type="text/javascript">
					script_ended = 1;
					if (top.fsMod) top.fsMod.recentIds["web"] = 0;
				</script>
			';

			$headerSection = $this->doc->getHeader('pages', $this->pageInfo, $this->pageInfo['_thePath']) . '<br />' . $LANG->sL('LLL:EXT:lang/locallang_core.xml:labels.path') . ': ' . t3lib_div::fixed_lgd_cs($this->pageInfo['_thePath'], -50);

			$this->content .= $this->doc->startPage($LANG->getLL('title'));
			$this->content .= $this->doc->header($LANG->getLL('title'));
			$this->content .= $this->doc->spacer(5);
			
			// Check if function setting for this page is set in session data
			if ($this->my_vars['pid' . $this->id]['function']) {
				$this->selectedFunction = $this->my_vars['pid' . $this->id]['function'];
			} else {
				$this->selectedFunction = $this->MOD_SETTINGS['function'];
			}
			// Check if sub module setting for this page is set in session data
			if ($this->my_vars['pid' . $this->id]['submodule']) {
				$this->subModule = $this->my_vars['pid' . $this->id]['submodule'];
			} else {
				$this->subModule = intval(t3lib_div::_GP('subModule'));
			}
			
			// Write function setting to session data if menu function was selected
			$setParams = t3lib_div::_GP('SET');
			if ($setParams['function']) {
				$this->selectedFunction = intval($setParams['function']);
				$this->my_vars['pid' . $this->id]['function'] = $this->selectedFunction;
				$GLOBALS["BE_USER"]->setAndSaveSessionData ('tx_wseevents', $this->my_vars);
			}
			// Write sub module setting to session data if sub module tab was selected
			if (t3lib_div::_GP('subModule')) {
				$this->subModule = intval(t3lib_div::_GP('subModule'));
				$this->my_vars['pid' . $this->id]['submodule'] = $this->subModule;
				$GLOBALS["BE_USER"]->setAndSaveSessionData ('tx_wseevents', $this->my_vars);
			}
			
			// menu output
			$this->content .= $this->doc->section('', 
							$this->doc->funcMenu($headerSection, 
									t3lib_BEfunc::getFuncMenu($this->id, 
											'SET[function]', 
											$this->selectedFunction, 
											$this->MOD_MENU['function'])));
			$this->content .= $this->doc->divider(5);

			// Render content:
			$this->moduleContent();

			// For debuging purpose only
/*			$debugline = '<br /><br /><hr />
						<br />##// DEBUG ###<br />This is the GET/POST vars sent to the script:<br /><br />'
						. 'GET:' . t3lib_utility_Debug::view_array($_GET) . '<br />'
						. 'POST:' . t3lib_utility_Debug::view_array($_POST) . '<br />'
#						. 'pageInfo:' . t3lib_utility_Debug::view_array($this->pageInfo) . '<br />'
#						. debug($_GET, 'GET:')
						. '';*/

#			$this->content .= $debugline;

			// ShortCut
			if ($BE_USER->mayMakeShortcut())	{
				$this->content .= $this->doc->spacer(20) . $this->doc->section('', $this->doc->makeShortcutIcon('id', implode(',', array_keys($this->MOD_MENU)), $this->MCONF['name']));
			}

			$this->content .= $this->doc->spacer(10);
		} else {
			// If no access or if ID == zero

			$this->doc = t3lib_div::makeInstance('mediumDoc');
			$this->doc->backPath = $BACK_PATH;

			$this->content .= $this->doc->startPage($LANG->getLL('title'));
			$this->content .= $this->doc->header($LANG->getLL('title'));
			$this->content .= $this->doc->spacer(5);
			$this->content .= $this->doc->spacer(10);
			if (0 == $this->id) {
				$this->content .= $LANG->getLL('notOnRootpage');
			}
		}
	}

	/**
	 * Prints out the module HTML
	 *
	 * @return	void
	 */
	function printContent()	{
		$this->content .= $this->doc->endPage();
		echo $this->content;
	}

	/**
	 * Generates the module content
	 *
	 * @return	void
	 */
	function moduleContent()	{
		switch((string)$this->selectedFunction)	{
			case 1:
				$this->moduleEventContent();
			break;
			case 2:
				$this->moduleCommonContent();
			break;
			case 3:
				$this->moduleSessionPlanning();
			break;
		}
	}

	/**
	 * Generates the content for event data
	 *
	 * @return	void
	 */
	function moduleEventContent()	{
		global $BE_USER, $LANG;

		// define the sub modules that should be available in the tabmenu
		$this->availableSubModules = array();

		// only show the tabs if the back-end user has access to the corresponding tables
		if ($BE_USER->check('tables_select', 'tx_wseevents_sessions')) {
			$this->availableSubModules[1] = $LANG->getLL('subModuleTitle_sessions');
		}
		if ($BE_USER->check('tables_select', 'tx_wseevents_timeslots')) {
			$this->availableSubModules[2] = $LANG->getLL('subModuleTitle_time_slots');
		}
		if ($BE_USER->check('tables_select', 'tx_wseevents_speakerrestrictions')) {
			$this->availableSubModules[3] = $LANG->getLL('subModuleTitle_speaker_restrictions');
		}
		if ($BE_USER->check('tables_select', 'tx_wseevents_events')) {
			$this->availableSubModules[4] = $LANG->getLL('subModuleTitle_events');
		}

		// Read the selected sub module (from the tab menu) and make it available within this class.
//		$this->subModule = intval(t3lib_div::_GET('subModule'));

		// If $this->subModule is not a key of $this->availableSubModules,
		// set it to the key of the first element in $this->availableSubModules
		// so the first tab is activated.
		if (!array_key_exists($this->subModule, $this->availableSubModules)) {
			reset($this->availableSubModules);
			$this->subModule = key($this->availableSubModules);
		}

		// Only generate the tab menu if the current back-end user has the
		// rights to show any of the tabs.
		if ($this->subModule) {
			$this->content .= $this->doc->getTabMenu(array('id' => $this->id),
				'subModule',
				$this->subModule,
				$this->availableSubModules);
			$this->content .= $this->doc->spacer(5);
		}

		// Select which sub module to display.
		// If no sub module is specified, an empty page will be displayed.
		switch ($this->subModule) {
			case 1:
				$eventsList = t3lib_div::makeInstance('tx_wseevents_sessionslist', $this);
				$this->content .= $eventsList->show();
				break;
			case 2:
				$eventsList = t3lib_div::makeInstance('tx_wseevents_timeslotslist', $this);
				$this->content .= $eventsList->show();
				break;
			case 3:
				$eventsList = t3lib_div::makeInstance('tx_wseevents_speakerrestrictionslist', $this);
				$this->content .= $eventsList->show();
				break;
			case 4:
				$this->content .= '<br />';
				$eventsList = t3lib_div::makeInstance('tx_wseevents_eventslist', $this);
				$this->content .= $eventsList->show();
				break;
			default:
				$this->content .= '';
				break;
		}
	}

	/**
	 * Generates the content for common data
	 *
	 * @return	void
	 */
	function moduleCommonContent()	{
		global $BE_USER, $LANG;

		// define the sub modules that should be available in the tabmenu
		$this->availableSubModules = array();

		// only show the tabs if the back-end user has access to the corresponding tables
		if ($BE_USER->check('tables_select', 'tx_wseevents_speakers')) {
			$this->availableSubModules[1] = $LANG->getLL('subModuleTitle_speakers');
		}
		if ($BE_USER->check('tables_select', 'tx_wseevents_locations')) {
			$this->availableSubModules[2] = $LANG->getLL('subModuleTitle_locations');
		}
		if ($BE_USER->check('tables_select', 'tx_wseevents_rooms')) {
			$this->availableSubModules[3] = $LANG->getLL('subModuleTitle_rooms');
		}
		if ($BE_USER->check('tables_select', 'tx_wseevents_categories')) {
			$this->availableSubModules[4] = $LANG->getLL('subModuleTitle_categories');
		}

		// Read the selected sub module (from the tab menu) and make it available within this class.
//		$this->subModule = intval(t3lib_div::_GET('subModule'));

		// If $this->subModule is not a key of $this->availableSubModules,
		// set it to the key of the first element in $this->availableSubModules
		// so the first tab is activated.
		if (!array_key_exists($this->subModule, $this->availableSubModules)) {
			reset($this->availableSubModules);
			$this->subModule = key($this->availableSubModules);
		}

		// Only generate the tab menu if the current back-end user has the
		// rights to show any of the tabs.
		if ($this->subModule) {
			$this->content .= $this->doc->getTabMenu(array('id' => $this->id),
				'subModule',
				$this->subModule,
				$this->availableSubModules);
			$this->content .= $this->doc->spacer(5);
		}

		// Select which sub module to display.
		// If no sub module is specified, an empty page will be displayed.
		switch ($this->subModule) {
			case 1:
				$eventsList = t3lib_div::makeInstance('tx_wseevents_speakerslist', $this);
				$this->content .= $eventsList->show();
				break;
			case 2:
				$eventsList = t3lib_div::makeInstance('tx_wseevents_locationslist', $this);
				$this->content .= $eventsList->show();
				break;
			case 3:
				$eventsList = t3lib_div::makeInstance('tx_wseevents_roomslist', $this);
				$this->content .= $eventsList->show();
				break;
			case 4:
				$eventsList = t3lib_div::makeInstance('tx_wseevents_categorieslist', $this);
				$this->content .= $eventsList->show();
				break;
			default:
				$this->content .= '';
				break;
		}
	}


	/**
	 * Generates the content for session planning
	 *
	 * @return	void
	 */
	function moduleSessionPlanning()	{

		$eventsList = t3lib_div::makeInstance('tx_wseevents_sessionplanning', $this);
		$this->content .= $eventsList->show();
	}

}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/wse_events/mod1/index.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/wse_events/mod1/index.php']);
}


// Make instance:
$SOBE = t3lib_div::makeInstance('tx_wseevents_module1');
$SOBE->init();

// Include files?
foreach($SOBE->include_once as $INC_FILE)	include_once($INC_FILE);

$SOBE->main();
$SOBE->printContent();

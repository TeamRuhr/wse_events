<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007-2013 Michael Oehlhof <typo3@oehlhof.de>
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
 * FE Plugin 'WSE Events' for the 'wse_events' extension.
 * Displays session data as list and detail view
 * Displays speaker data as list and detail view
 * Displays time slot view
 *
 * @author	Michael Oehlhof <typo3@oehlhof.de>
 */

/**
 * To temporary show some debug output on live web site
 * it can be easily switched on via a TypoScript setting.
 * plugin.tx_wseevents_pi1.listTimeslotView.debug = 1
 */

/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 *
 *
 *   93: class tx_wseevents_pi1 extends tslib_pibase
 *  121:     function main($content, $conf)
 *  221:     function listSessionView($content, $conf)
 *  503:     function listSpeakerView($content, $conf)
 *  721:     function listTimeslotView($content, $conf)
 * 1321:     function singleSessionView($content, $conf)
 * 1411:     function singleSpeakerView($content, $conf)
 * 1567:     function getFieldContent($fN)
 * 1809:     function getSpeakerSessionList($speakerid, $eventPid)
 * 1835:     function getFieldHeader($fN)
 * 1867:     function getRoomInfo($loc_id)
 * 1891:     function getSlot($event, $day, $room, $slot, $showdbgsql)
 * 1913:     function getSlotLength($slot_id)
 * 1928:     function getSlotSession($slot_id)
 * 1963:     function getSpeakerNames($speakerlist)
 * 1997:     function setCache()
 *
 * TOTAL FUNCTIONS: 16
 * (This index is automatically created/updated by the extension "extdeveval")
 *
 */

require_once(t3lib_extMgm::extPath('static_info_tables') . 'pi1/class.tx_staticinfotables_pi1.php');
require_once(t3lib_extMgm::extPath('wse_events') . 'class.tx_wseevents_events.php');

define('TAB', chr(9));
define('LF', chr(10));

/**
 * Class 'tx_wseevents_pi1' for the 'wse_events' extension.
 *
 * @package		TYPO3
 * @subpackage	wse_events
 * @author		Michael Oehlhof <typo3@oehlhof.de>
 */
class tx_wseevents_pi1 extends tslib_pibase {
	// Same as class name
	var $prefixId = 'tx_wseevents_pi1';

	// Path to this script relative to the extension dir.
	var $scriptRelPath = 'pi1/class.tx_wseevents_pi1.php';

	// The extension key.
	var $extKey = 'wse_events';

	//
	var $pi_checkCHash = TRUE;

	// Flag for using the cache
	var $useCache = 1;

	// Flag for displaying lists, used for Backlink creation
	var $listView = 1;

	// Internal configuration
	var $internal = array();

	/**
	 * TimeSlot class
	 * @var tx_wseevents_timeslots
	 */
	var $eventTimeSlots;

	/**
	 * Event class
	 * @var tx_wseevents_events
	 */
	var $events;

	//
	public $templateCode;
	public $staticInfo;
	public $documentsTarget;
	public $eventRecord;

	/**
	 * Main function, decides in which form the data is displayed
	 *
	 * @param	string		$content default content string, ignore
	 * @param	array		$conf TypoScript configuration for the plugin
	 * @return	string		Content for output on the web site
	 */
	function main($content, $conf) {
		$this->conf=$conf;
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();

		// Initialize classes
		$this->events = t3lib_div::makeInstance('tx_wseevents_events');
		$this->eventTimeSlots = t3lib_div::makeInstance('tx_wseevents_timeslots');

		if (isset($this->piVars['download'])) {
			switch ($this->piVars['download']) {
				case 'iCal':
					if (!empty($this->piVars['eventUid'])) {
						$this->createICalFromEvent($this->piVars['eventUid']);
					} else {
						$this->createICalFromSession($this->piVars['sessionUid'], $this->piVars['slotUid']);
					}
					break;
			}
		}

		// Init and get the flexform data of the plugin
		$this->pi_initPIflexform();
		$piFlexForm = $this->cObj->data['pi_flexform'];
		$index = $GLOBALS['TSFE']->sys_language_uid;

		// Get FlexForm data
		$sDef = current($piFlexForm['data']);
		$lDef = array_keys($sDef);

		// Initialize Static Info
		$this->staticInfo = &t3lib_div::getUserObj('&tx_staticinfotables_pi1');
		if ($this->staticInfo->needsInit())	{
			$this->staticInfo->init();
		}
		// Read TypoScript settings and initialize internal variables

		// Check if delimiter for speaker is set, if not use the default value
		if (!isset($conf['speakerdelimiter'])) {
			$this->internal['speakerdelimiter'] = '<br />';
		} else {
			$this->internal['speakerdelimiter'] = $conf['speakerdelimiter'];
		}
		// Check if delimiter for slots is set, if not use the default value
		if (!isset($conf['slotdelimiter'])) {
			$this->internal['slotdelimiter'] = '<br />';
		} else {
			$this->internal['slotdelimiter'] = $conf['slotdelimiter'];
		}
		// Check if delimiter for slots is set, if not use the default value
		if (!isset($conf['sessiondelimiter'])) {
			$this->internal['sessiondelimiter'] = '<br />';
		} else {
			$this->internal['sessiondelimiter'] = $conf['sessiondelimiter'];
		}
		// Check for hiding the time slots
		if (!isset($conf['hideTimeslots'])) {
			$this->internal['hideTimeslots'] = 0;
		} else {
			$this->internal['hideTimeslots'] = intval($conf['hideTimeslots']);
		}
		// Check if caching should be disabled
		if (isset($conf['no_cache']) && (1 == $conf['no_cache'])) {
			$this->useCache = 0;
		}

		$flexFormValuesArray['dynListType'] = $this->pi_getFFvalue($piFlexForm, 'dynListType', 'display', $lDef[0]);
		$conf['pidListEvents'] = $this->pi_getFFvalue($piFlexForm, 'pages', 'sDEF');
		$conf['pidListCommon'] = $this->pi_getFFvalue($piFlexForm, 'commonpages', 'sDEF');
		$conf['singleSession'] = $this->pi_getFFvalue($piFlexForm, 'singleSession', 'display');
		$conf['singleSpeaker'] = $this->pi_getFFvalue($piFlexForm, 'singleSpeaker', 'display');
		$conf['lastnameFirst'] = $this->pi_getFFvalue($piFlexForm, 'lastnameFirst', 'display');
		$conf['recursive'] = $this->cObj->data['recursive'];

		// Show input page depend on selected tab
		switch((string)$flexFormValuesArray['dynListType'])	{
			case 'sessionlist':
				$conf['pidList'] = $conf['pidListEvents'];
				return $this->pi_wrapInBaseClass($this->listSessionView($content, $conf));
			break;
			case 'sessiondetail':
				$this->listView = 0;
				// Set table to session table
				$this->internal['currentTable'] = 'tx_wseevents_sessions';
				$this->internal['currentRow'] = $this->piVars['showSessionUid'];
				return $this->pi_wrapInBaseClass($this->singleSessionView($content, $conf));
			break;
			case 'speakerlist':
				$conf['pidList'] = $conf['pidListCommon'];
				return $this->pi_wrapInBaseClass($this->listSpeakerView($content, $conf));
			break;
			case 'speakerdetail':
				$this->listView = 0;
				$this->internal['currentTable'] = 'tx_wseevents_speakers';
				$this->internal['currentRow'] = $this->piVars['showSpeakerUid'];
				return $this->pi_wrapInBaseClass($this->singleSpeakerView($content, $conf));
			break;
			case 'timeslots':
				return $this->pi_wrapInBaseClass($this->listTimeSlotView($content, $conf));
			break;
			default:
				return $this->pi_wrapInBaseClass('Not implemented: ['
					. (string)$flexFormValuesArray['dynListType'] . ']<br>Index=[' . $index . ']<br>');
			break;
		}
	}


	/**
	 * Create an iCalendar string out of the session data
	 *
	 * @param $sessionUid
	 * @param $slotUid
	 * @internal param array $session the uid of a session
	 * @internal param array $slot the uid of the timeslot of the session
	 * @return void
	 */
	function createICalFromSession($sessionUid, $slotUid){
		// load session data
		$where = 'uid=' . $sessionUid;
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_wseevents_sessions', $where);
		$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		// Get overload workspace record
		$GLOBALS['TSFE']->sys_page->versionOL('tx_wseevents_sessions', &$row);
		// fix pid for record from workspace
		$GLOBALS['TSFE']->sys_page->fixVersioningPid('tx_wseevents_sessions', &$row);
		// Get overload language record
		if ($GLOBALS['TSFE']->sys_language_content) {
			$row = $GLOBALS['TSFE']->sys_page->getRecordOverlay('tx_wseevents_sessions',
				$row, $GLOBALS['TSFE']->sys_language_content,
				$GLOBALS['TSFE']->sys_language_contentOL, '');
		}
		$session = $row;
		$GLOBALS['TYPO3_DB']->sql_free_result($res);
		$dataCat = $this->pi_getRecord('tx_wseevents_categories', $row['category']);
//		$dataCat = $this->eventTimeSlots->getCategory($row['category']);
		$category = $dataCat['shortkey'] . sprintf ('%02d', $row['number']);
		// load timeslot data
		$where = 'uid=' . $slotUid;
		$resSlot = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_wseevents_timeslots', $where);
		$slot = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($resSlot);
		$GLOBALS['TYPO3_DB']->sql_free_result($resSlot);
		// Create the iCal data
		$CrLf = chr(13) . chr(10);
		$event = $this->events->getEventInfo($session['event']);
		$eventRooms = $this->events->getEventRooms($session['event']);
		$secOfDay = 60*60*24;
		$thisDay = date('Ymd', $event['begin'] + ($slot['eventday'] - 1) * $secOfDay);
		$beginDay = intval(substr($event['timebegin'], 0, 2)) * 60 + intval(substr($event['timebegin'], 3, 2));
		$beginSlotVal = intval($beginDay) + (intval($slot['begin'] - 1) * intval($event['slotsize']));
		$beginSlot = strval($beginSlotVal % 60);
		if (strlen($beginSlot) == 1) {
			$beginSlot = '0' . $beginSlot;
		}
		$beginSlot = strval(floor($beginSlotVal / 60)) . $beginSlot;
		if (strlen($beginSlot) == 3) {
			$beginSlot = '0' . $beginSlot;
		}
		$endSlotVal = intval($beginSlotVal) + (intval($slot['length']) * intval($event['slotsize']));
		$endSlot = strval($endSlotVal % 60);
		if (strlen($endSlot) == 1) {
			$endSlot = '0' . $endSlot;
		}
		$endSlot = strval(floor($endSlotVal / 60)) . $endSlot;
		if (strlen($endSlot) == 3) {
			$endSlot = '0' . $endSlot;
		}
		$iCal = 'BEGIN:VCALENDAR' . $CrLf . 'VERSION:2.0' . $CrLf . 'METHOD:PUBLISH' . $CrLf;
		$iCal = $iCal . 'BEGIN:VEVENT' . $CrLf;
		$iCal = $iCal . 'LOCATION:' . $eventRooms[$slot['room']] . $CrLf;
		$iCal = $iCal . 'SUMMARY:' . $session['name'] . $CrLf;
		$iCal = $iCal . 'DESCRIPTION:' . $session['description'] . $CrLf;
		$iCal = $iCal . 'DTSTART:' . $thisDay . 'T' . $beginSlot . '00' . $CrLf;
		$iCal = $iCal . 'DTEND:' . $thisDay . 'T' . $endSlot . '00' . $CrLf;
		$iCal = $iCal . 'DTSTAMP:' . date('Ymd\THis') . $CrLf;
		$iCal = $iCal . 'END:VEVENT' . $CrLf . 'END:VCALENDAR' . $CrLf;
		/*
		 BEGIN:VCALENDAR
		 VERSION:2.0
		 PRODID:http://www.example.com/calendarapplication/
		 METHOD:PUBLISH
		 BEGIN:VEVENT
		 UID:461092315540@example.com
		 ORGANIZER:CN="Alice Balder, Example Inc.":MAILTO:alice@example.com
		 LOCATION:Somewhere
		 SUMMARY:Eine Kurzinfo
		 DESCRIPTION:Beschreibung des Termines
		 CLASS:PUBLIC
		 DTSTART:20060910T220000Z
		 DTEND:20060919T215900Z
		 DTSTAMP:20060812T125900Z
		 END:VEVENT
		 END:VCALENDAR
		 */
		ob_clean();
		header("Cache-Control: cache, must-revalidate");
		header("Pragma: public");
		header("Content-type: text/calendar");
		header("Content-Disposition: attachment; filename=" . $category . ".ics");
		echo $iCal;
		die();
	}


	/**
	 * Create an iCalendar string out of the event data
	 *
	 * @param $eventUid
	 * @internal param array $event the uid of the event
	 * @return void
	 */
	function createICalFromEvent($eventUid){
		// Create the iCal data
		$CrLf = chr(13) . chr(10);
		$event = $this->events->getEventInfo($eventUid);
		$beginDate = date('Ymd', $event['begin']);
		$beginDay = intval(substr($event['timebegin'], 0, 2)) * 60 + intval(substr($event['timebegin'], 3, 2));
		$beginSlot = strval($beginDay % 60);
		if (strlen($beginSlot) == 1) {
			$beginSlot = '0' . $beginSlot;
		}
		$beginSlot = strval(floor($beginDay / 60)) . $beginSlot;
		if (strlen($beginSlot) == 3) {
			$beginSlot = '0' . $beginSlot;
		}
		$endDate = date('Ymd', $event['begin'] + ($event['length'] * 60*60*24));
		$endDay = intval(substr($event['timeend'], 0, 2)) * 60 + intval(substr($event['timeend'], 3, 2));
		$endSlot = strval($endDay % 60);
		if (strlen($endSlot) == 1) {
			$endSlot = '0' . $endSlot;
		}
		$endSlot = strval(floor($endDay / 60)) . $endSlot;
		if (strlen($endSlot) == 3) {
			$endSlot = '0' . $endSlot;
		}
		// load location data
		$where = 'uid=' . $event['location'];
		$resLocation = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_wseevents_locations', $where);
		$location = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($resLocation);
		$GLOBALS['TYPO3_DB']->sql_free_result($resLocation);

		$iCal = 'BEGIN:VCALENDAR' . $CrLf . 'VERSION:2.0' . $CrLf . 'METHOD:PUBLISH' . $CrLf;
		$iCal = $iCal . 'BEGIN:VEVENT' . $CrLf;
		$iCal = $iCal . 'LOCATION:' . $location['name'] . $CrLf;
		$iCal = $iCal . 'SUMMARY:' . $event['name'] . $CrLf;
		$iCal = $iCal . 'DESCRIPTION:' . $event['comment'] . $CrLf;
		$iCal = $iCal . 'DTSTART:' . $beginDate . 'T' . $beginSlot . '00' . $CrLf;
		$iCal = $iCal . 'DTEND:' . $endDate . 'T' . $endSlot . '00' . $CrLf;
		$iCal = $iCal . 'DTSTAMP:' . date('Ymd\THis') . $CrLf;
		$iCal = $iCal . 'END:VEVENT' . $CrLf . 'END:VCALENDAR' . $CrLf;
		ob_clean();
		header("Cache-Control: cache, must-revalidate");
		header("Pragma: public");
		header("Content-type: text/calendar");
		header("Content-Disposition: attachment; filename=\"" . $event['name'] . ".ics\"");
		echo $iCal;
		die();
	}


	/**
	 * Display a list of sessions for the event that is set in the flex form settings
	 *
	 * @param	string		$content: default content string, ignore
	 * @param	array		$conf: TypoScript configuration for the plugin
	 * @return	string		Content for output on the web site
	 */
	function listSessionView($content, $conf)	{
		global $TCA;

		$this->conf=$conf;		// Setting the TypoScript passed to this function in $this->conf
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();		// Loading the LOCAL_LANG values

		$lConf = $this->conf['listView.'];	// Local settings for the listView function

		// Set table to session table
		$this->internal['currentTable'] = 'tx_wseevents_sessions';

		// Loading all TCA details for this table:
		t3lib_div::loadTCA($this->internal['currentTable']);

		if (!isset($this->piVars['pointer'])) $this->piVars['pointer']=0;
		if (!isset($this->piVars['mode'])) $this->piVars['mode']=1;

		// Check if template file is set, if not use the default template
		if (!isset($conf['templateFile'])) {
			$templateFile = 'EXT:wse_events/wseevents.tmpl';
		} else {
			$templateFile = $conf['templateFile'];
		}
		// Get the template
		$this->templateCode = $this->cObj->fileResource($templateFile);

		// Get the parts out of the template
		$template['total'] = $this->cObj->getSubpart($this->templateCode, '###SESSIONLIST###');
		$template['catsection'] = $this->cObj->getSubpart($template['total'], '###CATEGORYSELECT###');
		$template['catselect'] = $this->cObj->getSubpart($template['catsection'], '###SELECT###');
		$template['catoption'] = $this->cObj->getSubpart($template['catselect'], '###OPTIONNOTSELECTED###');
		$template['catoptionsel'] = $this->cObj->getSubpart($template['catselect'], '###OPTIONSELECTED###');
		$template['evtsection'] = $this->cObj->getSubpart($template['total'], '###EVENTSELECT###');
		$template['evtselect'] = $this->cObj->getSubpart($template['evtsection'], '###SELECT###');
		$template['evtoption'] = $this->cObj->getSubpart($template['evtselect'], '###OPTIONNOTSELECTED###');
		$template['evtoptionsel'] = $this->cObj->getSubpart($template['evtselect'], '###OPTIONSELECTED###');
		$template['singlerow'] = $this->cObj->getSubpart($template['total'], '###SINGLEROW###');
		$template['header'] = $this->cObj->getSubpart($template['singlerow'], '###HEADER###');
		$template['row'] = $this->cObj->getSubpart($template['singlerow'], '###ITEM###');
		$template['row_alt'] = $this->cObj->getSubpart($template['singlerow'], '###ITEM_ALT###');

		// Check if target for documents link is set, if not use the default target
		if (!isset($conf['documentsTarget'])) {
			$this->documentsTarget = 'target="_blank"';
		} else {
			$this->documentsTarget = $conf['documentsTarget'];
		}
		// Check for delimiter between the documents
		if (!isset($conf['documentsdelimiter'])) {
			$this->internal['documentsdelimiter'] = '<br />';
		} else {
			$this->internal['documentsdelimiter'] = $conf['documentsdelimiter'];
		}

		// Initializing the query parameters:
//		$sorting = $this->conf['sorting'];
		// Number of results to show in a listing.
		if (class_exists('t3lib_utility_Math')) {
			$this->internal['results_at_a_time'] = t3lib_utility_Math::forceIntegerInRange($lConf['results_at_a_time'], 0, 1000, 100);
			// The maximum number of "pages" in the browse-box: "Page 1", 'Page 2', etc.
			$this->internal['maxPages'] = t3lib_utility_Math::forceIntegerInRange($lConf['maxPages'], 0, 1000, 2);
		} else {
			$this->internal['results_at_a_time'] = t3lib_div::intInRange($lConf['results_at_a_time'], 0, 1000, 100);
			// The maximum number of "pages" in the browse-box: "Page 1", 'Page 2', etc.
			$this->internal['maxPages'] = t3lib_div::intInRange($lConf['maxPages'], 0, 1000, 2);
		}
		$this->internal['searchFieldList'] = 'uid, name, category, number, speaker, room, timeslots, teaser';
		$this->internal['orderByList'] = 'category, number, name';
	    $where = ' AND ' . $this->internal['currentTable'] . '.'
			. $TCA[$this->internal['currentTable']]['ctrl']['languageField'] . '=0';

		// Check for category selection
		$showCat = $this->piVars['showCategory'];
		if (!empty($showCat)) {
			$where .= ' AND category=' . $showCat;
		} else {
			$showCat = 0;
		}

		// Check for hidden catagories
		$hideCat = $conf['showSessionList.']['hideCategories'];
		if (empty($hideCat)) {
			$hideCat = 0;
		}

		// Check for event selection in URL
		$showEvent = $this->piVars['showEvent'];
		if (empty($showEvent)) {
			$showEvent = 0;
		}

		// Check for amount of events
		$this->conf['pidList'] = $this->conf['pidListEvents'];
		$where1 = ' AND sys_language_uid = 0';
		$res = $this->pi_exec_query('tx_wseevents_events', 1, $where1, '', '', 'name, uid');
		list($eventCount) = $GLOBALS['TYPO3_DB']->sql_fetch_row($res);

		// Show selection combo box if more than one event is found
		if (1 < $eventCount) {
			// Create template data for event combobox
			$event_item = '';	// Clear var;
			$markerArray = array();
			// Make listing query, pass query to SQL database:
			$res = $this->pi_exec_query('tx_wseevents_events', 0, $where1);
			if ($res) {
				while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
					// Get overload language record
					if ($GLOBALS['TSFE']->sys_language_content) {
						$row = $GLOBALS['TSFE']->sys_page->getRecordOverlay('tx_wseevents_events',
							$row, $GLOBALS['TSFE']->sys_language_content,
							$GLOBALS['TSFE']->sys_language_contentOL, '');
					}

					// Take the first event as selected if no event is selected in the URL
					if (0 == $showEvent) {
						$showEvent = $row['uid'];
					}
					$eventName = $row['name'];

					// Set one event option
					$markerArray['###VALUE###'] = $row['uid'];
					$markerArray['###OPTION###'] = $eventName;
					if ($showEvent == $row['uid']) {
						$event_item .= $this->cObj->substituteMarkerArrayCached($template['evtoptionsel'], $markerArray);
					} else {
						$event_item .= $this->cObj->substituteMarkerArrayCached($template['evtoption'], $markerArray);
					}
				}
				$GLOBALS['TYPO3_DB']->sql_free_result($res);
			}
			// Set select options
			$subPartArray1['###SELECT###'] = $event_item;
			// Set label for selection box
			$markerArray1['###LABEL###'] = $this->pi_getLL('tx_wseevents_sessions.chooseeventday', '[Choose event day]');
			$markerArray1['###FORMSELECT###'] = $this->prefixId . '[showEvent]';
			$markerArray1['###FORMSEND###'] = htmlspecialchars($this->pi_getLL('tx_wseevents_sessions.showselection', '[Show selection]'));
			$subPartArray['###EVENTSELECT###'] = $this->cObj->substituteMarkerArrayCached($template['evtsection'], $markerArray1, $subPartArray1);
		} else {
			$subPartArray['###EVENTSELECT###'] = '';
		}

		// Get date of event
		$this->eventRecord = $this->events->getEventInfo($showEvent);

		// Create template data for category combobox
		$select_item = '';	// Clear var;
		$markerArray = array();
		$markerArray['###VALUE###'] = 0;
		$markerArray['###OPTION###'] = $this->pi_getLL('tx_wseevents_sessions.chooseall', '[-All-]');
		if (0 == $showCat) {
			$select_item .= $this->cObj->substituteMarkerArrayCached($template['catoptionsel'], $markerArray);
		} else {
			$select_item .= $this->cObj->substituteMarkerArrayCached($template['catoption'], $markerArray);
		}

		// Get list of categories
		// Make query, pass query to SQL database:
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_wseevents_categories', 'sys_language_uid=0'
			. $this->cObj->enableFields('tx_wseevents_categories'), '', 'shortkey');
		if ($res) {
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				if (!t3lib_div::inList($hideCat, $row['uid'])) {
					// Get overload language record
					if ($GLOBALS['TSFE']->sys_language_content) {
						$row = $GLOBALS['TSFE']->sys_page->getRecordOverlay('tx_wseevents_categories',
							$row, $GLOBALS['TSFE']->sys_language_content,
							$GLOBALS['TSFE']->sys_language_contentOL, '');
					}
					$catName = $row['name'];
					// Set one category option
					$markerArray['###VALUE###'] = $row['uid'];
					$markerArray['###OPTION###'] = $row['shortkey'] . ' - ' . $catName;
					if ($showCat==$row['uid']) {
						$select_item .= $this->cObj->substituteMarkerArrayCached($template['catoptionsel'], $markerArray);
					} else {
						$select_item .= $this->cObj->substituteMarkerArrayCached($template['catoption'], $markerArray);
					}
				}
			}
			$GLOBALS['TYPO3_DB']->sql_free_result($res);
		}
		// Set select options
		$subPartArray1['###SELECT###'] = $select_item;
		// Set label for selection box
		$markerArray1['###LABEL###'] = $this->pi_getLL('tx_wseevents_sessions.choosecategory', '[Choose category]');
		$markerArray1['###FORMSELECT###'] = $this->prefixId . '[showCategory]';
		$markerArray1['###FORMSEND###'] = htmlspecialchars($this->pi_getLL('tx_wseevents_sessions.showselection', '[Show selection]'));
		$subPartArray['###CATEGORYSELECT###'] = $this->cObj->substituteMarkerArrayCached($template['catsection'], $markerArray1, $subPartArray1);

		// Get number of records:
		$this->conf['pidList'] = $this->conf['pidListEvents'];
		$res = $this->pi_exec_query($this->internal['currentTable'], 1, $where, '', '', 'category, number, name');
		list($this->internal['res_count']) = $GLOBALS['TYPO3_DB']->sql_fetch_row($res);

		// Make listing query, pass query to SQL database:
		$res = $this->pi_exec_query($this->internal['currentTable'], 0, $where, '', '', 'category, number, name');

		// Get the column names
		$content_item = '';	// Clear var;
		$markerArray = array();
		$markerArray['###SESSIONNUMBER###'] = $this->getFieldHeader('number');
		$markerArray['###SESSIONNAME###'] = $this->getFieldHeader('name');
		$markerArray['###SPEAKER###'] = $this->getFieldHeader('speaker');
		$markerArray['###TIMESLOTS###'] = $this->getFieldHeader('timeslots');
		$markerArray['###SESSIONDOCUMENTSNAME###'] = $this->getFieldHeader('documents');

		$content_item .= $this->cObj->substituteMarkerArrayCached($template['header'], $markerArray);

		$switch_row = 0;
		if ($res) {
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				// Get overload workspace record
				$GLOBALS['TSFE']->sys_page->versionOL($this->internal['currentTable'], &$row);
				// fix pid for record from workspace
				$GLOBALS['TSFE']->sys_page->fixVersioningPid($this->internal['currentTable'], &$row);
				// Get overload language record
				if ($GLOBALS['TSFE']->sys_language_content) {
					$row = $GLOBALS['TSFE']->sys_page->getRecordOverlay($this->internal['currentTable'],
						$row, $GLOBALS['TSFE']->sys_language_content,
						$GLOBALS['TSFE']->sys_language_contentOL, '');
				}
				// show only sessions of selected event
				if (0 < $showEvent) {
					if ($showEvent <> $row['event']) {
						unset ($row);
					}
				}
				if (isset($row)) {
					if (is_array($row)) {
						$this->internal['currentRow'] = $row;
						if (!t3lib_div::inList($hideCat, $row['category'])) {
							if (!empty($this->conf['singleSession'])) {
								$label = $this->getFieldContent('name');		// the link text
								$overrulePiVars = array('showSessionUid' => $this->internal['currentRow']['uid'],
									'backUid' => $GLOBALS['TSFE']->id);
								$clearAnyway = 1;				// the current values of piVars will NOT be preserved
								$altPageId = $this->conf['singleSession'];		// ID of the target page, if not on the same page
								$this->setCache();
								$sessionName = $this->pi_linkTP_keepPIvars($label, $overrulePiVars,
									$this->useCache, $clearAnyway, $altPageId);
							} else {
								$sessionName = $this->getFieldContent('name');
							}

							// Build content from template + array
							$markerArray = array();
							$markerArray['###SESSIONTEASERNAME###'] = $this->getFieldHeader('teaser');
							$markerArray['###SESSIONTEASER###'] = $this->getFieldContent('teaser');
							$markerArray['###SESSIONDESCRIPTIONNAME###'] = $this->getFieldHeader('description');
							$markerArray['###SESSIONDESCRIPTION###'] = $this->cObj->stdWrap($this->getFieldContent('description'),
								$this->conf['sessiondescription_stdWrap.']);

							$markerArray['###SESSIONDOCUMENTSNAME###'] = $this->getFieldHeader('documents');
							$markerArray['###SESSIONDOCUMENTS###'] = $this->getFieldContent('documents');
							$markerArray['###SESSIONNAME###'] = $sessionName;
							$markerArray['###SPEAKER###'] = $this->getFieldContent('speaker');
							$markerArray['###TIMESLOTS###'] = $this->getFieldContent('timeslots');

							$markerArray['###SESSIONNUMBER###'] = $this->getFieldContent('number');
							// Get the data for the category of the session
							$dataCat  = $this->pi_getRecord('tx_wseevents_categories', $this->getFieldContent('category'));
							$markerArray['###SESSIONCATEGORY###'] = $this->getFieldContent('category');
							$markerArray['###SESSIONCATEGORYKEY###'] = $dataCat['shortkey'];
							$markerArray['###SESSIONCATEGORYCOLOR###'] = $dataCat['color'];

							$switch_row = $switch_row ^ 1;
							if($switch_row) {
								$content_item .= $this->cObj->substituteMarkerArrayCached($template['row'], $markerArray);
							} else {
								$content_item .= $this->cObj->substituteMarkerArrayCached($template['row_alt'], $markerArray);
							}
						}
					}
				}
			}
			$GLOBALS['TYPO3_DB']->sql_free_result($res);
		}
		$subPartArray['###SINGLEROW###'] = $content_item;

		$content .= $this->cObj->substituteMarkerArrayCached($template['total'], array(), $subPartArray);
		return $content;
	}


	/**
	 * Display a list of speakers for the event that is set in the flex form settings
	 *
	 * @param	string		$content default content string, ignore
	 * @param	array		$conf TypoScript configuration for the plugin
	 * @return	string		Content for output on the web site
	 */
	function listSpeakerView($content, $conf)	{
		$this->conf=$conf;		// Setting the TypoScript passed to this function in $this->conf
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();		// Loading the LOCAL_LANG values

		$lConf = $this->conf['listView.'];	// Local settings for the listView function

		// Set table to session table
		$this->internal['currentTable'] = 'tx_wseevents_speakers';

		if (!isset($this->piVars['pointer']))	$this->piVars['pointer'] = 0;
		if (!isset($this->piVars['mode']))	$this->piVars['mode'] = 1;

		// Initializing the query parameters:
//		$sorting = $this->conf['sorting'];
		// Number of results to show in a listing.
		if (class_exists('t3lib_utility_Math')) {
			$this->internal['results_at_a_time'] = t3lib_utility_Math::forceIntegerInRange($lConf['results_at_a_time'], 0, 1000, 100);
			// The maximum number of "pages" in the browse-box: "Page 1", 'Page 2', etc.
			$this->internal['maxPages'] = t3lib_utility_Math::forceIntegerInRange($lConf['maxPages'], 0, 1000, 2);
		} else {
			$this->internal['results_at_a_time'] = t3lib_div::intInRange($lConf['results_at_a_time'], 0, 1000, 100);
			// The maximum number of "pages" in the browse-box: "Page 1", 'Page 2', etc.
			$this->internal['maxPages'] = t3lib_div::intInRange($lConf['maxPages'], 0, 1000, 2);
		}
		$this->internal['searchFieldList'] = 'name, firstname, email, info, uid';
		$this->internal['orderByList'] = 'name, firstname, email, info, uid';
		$this->internal['orderBy'] = 'name, firstname';
		$this->internal['descFlag'] = 0;
		// Check for setting sort order via TypoScript
		if (isset($this->conf['sortSpeakerlist'])) {
			list($this->internal['orderBy'], $this->internal['descFlag']) = explode(':', $this->conf['sortSpeakerlist']);
		}

		$where = ' AND ' . $this->internal['currentTable'] . '.sys_language_uid = 0';

		// Get number of records:
		$res = $this->pi_exec_query($this->internal['currentTable'], 1, $where, '', '', 'name');
		list($this->internal['res_count']) = $GLOBALS['TYPO3_DB']->sql_fetch_row($res);

		// Make listing query, pass query to SQL database:
		$res = $this->pi_exec_query($this->internal['currentTable'], 0, $where);

		// Check if upload directory is set, if not use the default directory
		if (!isset($conf['uploadDirectory'])) {
			$uploadDirectory = 'uploads/tx_wseevents';
		} else {
			$uploadDirectory = $conf['uploadDirectory'];
		}

		// Check if template file is set, if not use the default template
		if (!isset($conf['templateFile'])) {
			$templateFile = 'EXT:wse_events/wseevents.tmpl';
		} else {
			$templateFile = $conf['templateFile'];
		}
		// Get the template
		$this->templateCode = $this->cObj->fileResource($templateFile);

		// Get the parts out of the template
		$template['total']      = $this->cObj->getSubpart($this->templateCode,    '###SPEAKERLIST###');
		$template['header']     = $this->cObj->getSubpart($template['total'],     '###HEADER###');
		$template['singlerow']  = $this->cObj->getSubpart($template['total'],     '###SINGLEROW###');
		$template['row']        = $this->cObj->getSubpart($template['singlerow'], '###ITEM###');
		$template['row_alt']    = $this->cObj->getSubpart($template['singlerow'], '###ITEM_ALT###');
		$template['sessionrow'] = $this->cObj->getSubpart($template['singlerow'], '###SESSIONROW###');

		// Put the whole list together:
		$content_item = '';	// Clear var;

		// Get the column names
		$markerArray0 = Array();
		$markerArray0['###SPEAKERNAME###']  = $this->getFieldHeader('name');
		$markerArray0['###EMAILNAME###']    = $this->getFieldHeader('email');
		$markerArray0['###COUNTRYNAME###']  = $this->getFieldHeader('country');
		$markerArray0['###COMPANYNAME###']  = $this->getFieldHeader('company');
		$markerArray0['###INFONAME###']     = $this->getFieldHeader('info');
		$markerArray0['###IMAGENAME###']    = $this->getFieldHeader('image');
		$markerArray0['###SESSIONSNAME###'] = $this->getFieldHeader('speakersessions');

		$subPartArray['###HEADER###']       = $this->cObj->substituteMarkerArrayCached($template['header'], $markerArray0);

		$switch_row = 0;
		if ($res) {
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				// Get overload workspace record
				$GLOBALS['TSFE']->sys_page->versionOL($this->internal['currentTable'], &$row);
				// fix pid for record from workspace
				$GLOBALS['TSFE']->sys_page->fixVersioningPid($this->internal['currentTable'], &$row);
				// Get overload language record
				if ($GLOBALS['TSFE']->sys_language_content) {
					$row = $GLOBALS['TSFE']->sys_page->getRecordOverlay($this->internal['currentTable'],
						$row, $GLOBALS['TSFE']->sys_language_content,
						$GLOBALS['TSFE']->sys_language_contentOL, '');
				}
				$this->internal['currentRow'] = $row;
				// Check if the speaker has a session on this event
				$sessionIds = $this->getSpeakerSessionList($this->internal['currentRow']['uid'], $this->conf['pidListEvents']);

				// display only speaker with sessions
				if (!empty($sessionIds)) {
					// Check if link to detail view is set
					if (!empty($this->conf['singleSpeaker'])) {
						$label = $this->getFieldContent('name');  // the link text
//						$overrulePiVars = '';//array('session' => $this->getFieldContent('uid'));
						$overrulePiVars = array('showSpeakerUid' => $this->internal['currentRow']['uid'], 'backUid' => $GLOBALS['TSFE']->id);
						$clearAnyway = 1;    // the current values of piVars will NOT be preserved
						$altPageId = $this->conf['singleSpeaker'];      // ID of the target page, if not on the same page
						$this->setCache();
						$speakerName = $this->pi_linkTP_keepPIvars($label, $overrulePiVars, $this->useCache, $clearAnyway, $altPageId);
					} else {
						$speakerName = $this->getFieldContent('name');
					}

					// remember sessionids for getFieldContent
					$this->internal['speakersessions'] = $sessionIds;

					// Build content from template + array
					$markerArray = Array();
					$markerArray['###SPEAKERNAME###'] = $speakerName;
					$markerArray['###IMAGENAME###'] = $this->getFieldContent('name');
					$markerArray['###EMAILNAME###'] = $this->getFieldHeader('email');
					$markerArray['###EMAILDATA###'] = $this->getFieldContent('email');
					$markerArray['###COUNTRYNAME###'] = $this->getFieldHeader('country');
					$markerArray['###COUNTRYDATA###'] = $this->getFieldContent('country');
					$markerArray['###COMPANYNAME###'] = $this->getFieldHeader('company');
					$markerArray['###COMPANYDATA###'] = $this->getFieldContent('company');
					$markerArray['###COMPANYLINK###'] = 'http://' . $this->getFieldContent('companylink');
					$markerArray['###SESSIONSNAME###'] = $this->getFieldHeader('speakersessions');
					$markerArray['###SESSIONS###'] = $this->getFieldContent('speakersessions');
					$markerArray['###INFONAME###'] = $this->getFieldHeader('info');
					$markerArray['###INFODATA###'] = $this->cObj->stdWrap($this->getFieldContent('info'),
						$this->conf['infodata_stdWrap.']);
					$markerArray['###IMAGENAME###'] = $this->getFieldHeader('image');

					$image = trim($this->getFieldContent('image'));
					if (!empty($image)) {
						$img = $this->conf['image.'];
						if (empty($img)) {
							$img['file'] = 'GIFBUILDER';
							$img['file.']['XY'] = '100, 150';
							$img['file.']['5'] = 'IMAGE';
						}
						$img['file.']['5.']['file'] = $uploadDirectory . '/' . $image;
						$markerArray['###IMAGELINK###'] = $this->cObj->IMAGE($img);
						$markerArray['###IMAGEFILE###'] = $uploadDirectory . '/' . $image;
					} else {
						$markerArray['###IMAGELINK###'] = '';
						$markerArray['###IMAGEFILE###'] = '';
					}

					// For every session get information
					$sessionContentItem = '';
					foreach (explode(',', $sessionIds) as $k){
						// Get session data record
						$sessionData = $this->pi_getRecord('tx_wseevents_sessions', $k);
						// Get overload language record
						if ($GLOBALS['TSFE']->sys_language_content) {
							$sessionData = $GLOBALS['TSFE']->sys_page->getRecordOverlay('tx_wseevents_sessions',
								$sessionData, $GLOBALS['TSFE']->sys_language_content,
								$GLOBALS['TSFE']->sys_language_contentOL, '');
						}

						$label = $sessionData['name'];
						if (!empty($this->conf['singleSession'])) {
							$overrulePiVars = array('showSessionUid' => $k, 'backUid' => $GLOBALS['TSFE']->id);
							$clearAnyway = 1;    // the current values of piVars will NOT be preserved
							$altPageId = $this->conf['singleSession'];      // ID of the target page, if not on the same page
							$this->setCache();
							$sessionName = $this->pi_linkTP_keepPIvars($label, $overrulePiVars,
								$this->useCache, $clearAnyway, $altPageId);
						} else {
							$sessionName = $label;
						}

						// Build content from template + array
						$markerArray1 = array();
						$markerArray1['###SESSIONNAME###'] = $sessionName;
						$markerArray1['###SESSIONTEASER###'] = $sessionData['teaser'];
						$markerArray1['###SESSIONDESCRIPTION###'] = $this->cObj->stdWrap($sessionData['description'],
							$this->conf['sessiondescription_stdWrap.']);
						$dataCat  = $this->pi_getRecord('tx_wseevents_categories', $sessionData['category']);
						$markerArray1['###SESSIONNUMBER###'] = $dataCat['shortkey'] . sprintf('%02d', $sessionData['number']);
						$markerArray1['###SESSIONCATEGORY###'] = $sessionData['category'];
						$markerArray1['###SESSIONCATEGORYKEY###'] = $dataCat['shortkey'];
						$markerArray1['###SESSIONCATEGORYCOLOR###'] = $dataCat['color'];
						// Get time slot info
						$timeSlotContent = '';
						if (0 == $this->internal['hideTimeslots']) {
							foreach (explode(',', $sessionData['timeslots']) as $ts){
								$timeSlotData = $this->pi_getRecord('tx_wseevents_timeslots', $ts);
								$timeSlotName = $this->eventTimeSlots->formatSlotName($timeSlotData);
								if (1 == $this->internal['showCalendarLink']) {
									// Create link for iCal download
									$overrulePiVars = array('sessionUid' => $k, 'slotUid' => $ts, 'download' => 'iCal');
									if (!empty($this->internal['calendarLinkLabel'])) {
										$label = $this->internal['calendarLinkLabel'];
									} else {
										$label = 'iCal';  // the link text
									}
									$iCalLinkName = $this->pi_linkTP_keepPIvars($label, $overrulePiVars, $this->useCache);
									$timeSlotName .= ' ' . $iCalLinkName;
								}
								if (!empty($timeSlotContent)) {
									$timeSlotContent .= $this->internal['slotdelimiter'] . $timeSlotName;
								} else {
									$timeSlotContent = $timeSlotName;
								}
							}
						}
						$markerArray1['###SESSIONSLOTS###'] = $timeSlotContent;

						$sessionContentItem .= $this->cObj->substituteMarkerArrayCached($template['sessionrow'], $markerArray1);
					}
					$subPartArraySession['###SESSIONROW###'] = $sessionContentItem;
					if (0 == $switch_row) {
						$content_item .= $this->cObj->substituteMarkerArrayCached($template['row'], $markerArray, $subPartArraySession);
					} else {
						$content_item .= $this->cObj->substituteMarkerArrayCached($template['row_alt'], $markerArray, $subPartArraySession);
					}
					if (!empty($template['row_alt'])) {
						$switch_row = $switch_row ^ 1;
					}
				}
			}
			$GLOBALS['TYPO3_DB']->sql_free_result($res);
		}
		$subPartArray['###SINGLEROW###'] = $content_item;

		$content .= $this->cObj->substituteMarkerArrayCached($template['total'], $markerArray0, $subPartArray);
		return $content;
	}








	/**
	 * Display a list of time slots for the event that is set in the flex form settings
	 *
	 * @param	string		$content default content string, ignore
	 * @param	array		$conf TypoScript configuration for the plugin
	 * @return	string		Content for output on the web site
	 */
	function listTimeSlotView($content, $conf)	{
		$this->conf=$conf;		// Setting the TypoScript passed to this function in $this->conf
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();		// Loading the LOCAL_LANG values
		$index = $GLOBALS['TSFE']->sys_language_uid;

//		$lConf = $this->conf['listView.'];	// Local settings for the listView function

		// Set table to session table
		$this->internal['currentTable'] = 'tx_wseevents_sessions';

		if (!isset($this->piVars['pointer'])) $this->piVars['pointer']=0;
		if (!isset($this->piVars['mode'])) $this->piVars['mode']=1;

		// Check for event day selection
		$showDay = $this->piVars['showDay'];

		// Check for event room selection
		$showRoom = $this->piVars['showRoom'];
		if (empty($showRoom)) {
			$showRoom = 0;
		}

		// Check for event category selection
		$showCategory = $this->piVars['showCategory'];

		// Check for event begin slot selection
		$showBegin = $this->piVars['showBegin'];

		// Check for event end slot selection
		$showEnd = $this->piVars['showEnd'];

		// Check for hidden catagory links
		$hideCat = $conf['listTimeslotView.']['hideCategoryLinks'];
		if (empty($hideCat)) {
			$hideCat = 0;
		}

		// Check for hidden display of "not assigned"
		$hideNotAssigned = $conf['listTimeslotView.']['hideNotAssigned'];
		if (empty($hideNotAssigned)) {
			$hideNotAssigned = 0;
		}

		// Check for hidden display of "not defined"
		$hideNotDefined = $conf['listTimeslotView.']['hideNotDefined'];
		if (empty($hideNotDefined)) {
			$hideNotDefined = 0;
		}

		// Check for hidden display of "Time"
		$hideTime = $conf['listTimeslotView.']['hideShowTime'];
		if (empty($hideTime)) {
			$hideTime = 0;
		}

		// Check for compact display of begin and end of sessions
		$roomTimeSetting = $conf['listTimeslotView.']['showRoomTime'];
		if (empty($roomTimeSetting)) {
			$roomTimeSetting = 0;
		}
		$roomTime = $roomTimeSetting;

		// Check for not assigned time slot color
		$catColorNotAssigned = $conf['listTimeslotView.']['categoryColorNotAssigned'];
		if (empty($catColorNotAssigned)) {
			$catColorNotAssigned = '#FFFFFF';
		}
		// Check for not defined time slot color
		$catColorNotDefined = $conf['listTimeslotView.']['categoryColorNotDefined'];
		if (empty($catColorNotDefined)) {
			$catColorNotDefined = '#FFFFFF';
		}

		// Check for given width of time column
		$timeColWidth = $conf['listTimeslotView.']['timeColWidth'];
		if (empty($timeColWidth)) {
			$timeColWidth = 0;
		}

		// Check for given width of column between days in "All days" view
		if (0 == $showDay) {
			$dayDelimiterWidth = $conf['listTimeslotView.']['dayDelimWidth'];
		}
		if (empty($dayDelimiterWidth)) {
			$dayDelimiterWidth = 0;
		}
		$dayDelimiterClass = $conf['listTimeslotView.']['dayDelimClass'];

		// Check for given width of event titles
		$teaserWidth = $conf['listTimeslotView.']['teaserWidth'];
		if (empty($teaserWidth)) {
			$teaserWidth = 0;
		}

		// For debugging output used in development
		$showDebug = $conf['listTimeslotView.']['debug'];
		if (empty($showDebug)) {
			$showDebug = 0;
		}

		// For debugging SQL output used in development
		$showDebugSql = $conf['listTimeslotView.']['debugsql'];
		if (empty($showDebugSql)) {
			$showDebugSql = 0;
		}

		// For hide rooms if no slots assigned
		$hideEmptyRooms = $conf['listTimeslotView.']['hideEmptyRooms'];
		if (empty($hideEmptyRooms)) {
			$hideEmptyRooms = 0;
		}

		// For showing the days vertically
		$showDaysVertical = $conf['listTimeslotView.']['showDaysVertical'];
		if (empty($showDaysVertical)) {
			$showDaysVertical = 0;
		}

		// For showing room selector
		$showRoomSelector = $conf['listTimeslotView.']['showRoomSelector'];
		if (empty($showRoomSelector)) {
			$showRoomSelector = 0;
		}

		// For showing category selector
		$showCategorySelector = $conf['listTimeslotView.']['showCategorySelector'];
		if (empty($showCategorySelector)) {
			$showCategorySelector = 0;
		}

		// For showing hour selector
		$showHourSelector = $conf['listTimeslotView.']['showHourSelector'];
		if (empty($showHourSelector)) {
			$showHourSelector = 0;
		}

		// For using jquery
		$useJQuery = $conf['listTimeslotView.']['useJQuery'];
		if (empty($useJQuery)) {
			$useJQuery = 0;
		}

		$timeSelect = $conf['listTimeslotView.']['timeSelect.'];
		if (empty($timeSelect)) {
			$timeSelect = array();
		}

		// Check if template file is set, if not use the default template
		if (!isset($conf['templateFile'])) {
			$templateFile = 'EXT:wse_events/wseevents.tmpl';
		} else {
			$templateFile = $conf['templateFile'];
		}
		// Get the template
		$this->templateCode = $this->cObj->fileResource($templateFile);

		// Get the parts out of the template
		$template['total']			= $this->cObj->getSubpart($this->templateCode, '###SLOTSDAY###');
		if ((empty($template['total'])) or (0 == $showDay)) {
			$template['total']		= $this->cObj->getSubpart($this->templateCode, '###SLOTSALL###');
		}
		if (1 == $useJQuery) {
			$template['titledayselect']		= $this->cObj->getSubpart($template['total'],		'###TITLEDAYSELECTJQUERY###');
			$template['titleroomselect']	= $this->cObj->getSubpart($template['total'],		'###TITLEROOMSELECTJQUERY###');
			$template['catsection']			= $this->cObj->getSubpart($template['total'],		'###CATEGORYSELECTJQUERY###');
			$template['selectdetail']		= $this->cObj->getSubpart($template['total'],		'###SELECTDETAIL###');
		} else {
			$template['titledayselect']		= $this->cObj->getSubpart($template['total'],		'###TITLEDAYSELECT###');
			$template['titleroomselect']	= $this->cObj->getSubpart($template['total'],		'###TITLEROOMSELECT###');
			$template['catsection']			= $this->cObj->getSubpart($template['total'],		'###CATEGORYSELECT###');
			$template['selectdetail']		= '';
		}
		$template['select']				= $this->cObj->getSubpart($template['titledayselect'],	'###SELECT###');
		$template['option']				= $this->cObj->getSubpart($template['select'],			'###OPTIONNOTSELECTED###');
		$template['optionsel']			= $this->cObj->getSubpart($template['select'],			'###OPTIONSELECTED###');
		$template['selectroom']			= $this->cObj->getSubpart($template['titleroomselect'],	'###SELECT###');
		$template['roomoption']			= $this->cObj->getSubpart($template['selectroom'],		'###OPTIONNOTSELECTED###');
		$template['roomoptionsel']		= $this->cObj->getSubpart($template['selectroom'],		'###OPTIONSELECTED###');
		$template['catselect']			= $this->cObj->getSubpart($template['catsection'],		'###SELECT###');
		$template['catoption']			= $this->cObj->getSubpart($template['catselect'],		'###OPTIONNOTSELECTED###');
		$template['catoptionsel']		= $this->cObj->getSubpart($template['catselect'],		'###OPTIONSELECTED###');
		$template['titlerow']		= $this->cObj->getSubpart($template['total'],		'###TITLEROW###');
		$template['titlecol']		= $this->cObj->getSubpart($template['titlerow'],	'###TITLECOLUMN###');
		$template['evtsection']		= $this->cObj->getSubpart($template['total'],		'###EVENTSELECT###');
		$template['evtselect']		= $this->cObj->getSubpart($template['evtsection'],	'###SELECT###');
		$template['evtoption']		= $this->cObj->getSubpart($template['evtselect'],	'###OPTIONNOTSELECTED###');
		$template['evtoptionsel']	= $this->cObj->getSubpart($template['evtselect'],	'###OPTIONSELECTED###');
//		$template['option']			= $this->cObj->getSubpart($template['select'],		'###OPTIONNOTSELECTED###');
//		$template['optionsel']		= $this->cObj->getSubpart($template['select'],		'###OPTIONSELECTED###');
		$template['headerrow']		= $this->cObj->getSubpart($template['total'],		'###HEADERROW###');
		$template['headercol']		= $this->cObj->getSubpart($template['headerrow'],	'###HEADERCOLUMN###');
		$template['headercolempty']	= $this->cObj->getSubpart($template['headerrow'],	'###HEADERCOLUMNEMPTY###');
		$template['slotrow']		= $this->cObj->getSubpart($template['total'],		'###SLOTROW###');
		$template['timecol']		= $this->cObj->getSubpart($template['slotrow'],		'###TIMECOLUMN###');
		$template['timecolfree']	= $this->cObj->getSubpart($template['slotrow'],		'###TIMECOLUMNEMPTY###');
		$template['slotcol']		= $this->cObj->getSubpart($template['slotrow'],		'###SLOTCOLUMN###');
		$template['slotcolempty']	= $this->cObj->getSubpart($template['slotrow'],		'###SLOTCOLUMNEMPTY###');

		// Check for event selection in URL
		$showEvent = $this->piVars['showEvent'];
		if (empty($showEvent)) {
			$showEvent = 0;
		}

		// Check for amount of events
		$this->conf['pidList'] = $this->conf['pidListEvents'];
	    $where1 = ' AND sys_language_uid = 0';
		if (1 == $showDebugSql) { echo 'SQL1:' . $where1 . '<br>'; };
		$res = $this->pi_exec_query('tx_wseevents_events', 1, $where1, '', '', 'name, uid');
		list($eventCount) = $GLOBALS['TYPO3_DB']->sql_fetch_row($res);

		// Create template data for event combobox
		$event_item = '';	// Clear var;
		$markerArray = array();
		// Make listing query, pass query to SQL database:
		if (1 == $showDebugSql) { echo 'SQL2:' . $where1 . '<br>'; };
		$res = $this->pi_exec_query('tx_wseevents_events', 0, $where1);
		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			// Get overload workspace record
			$GLOBALS['TSFE']->sys_page->versionOL('tx_wseevents_events', &$row);
			// fix pid for record from workspace
			$GLOBALS['TSFE']->sys_page->fixVersioningPid('tx_wseevents_events', &$row);
			// Get overload language record
			if ($GLOBALS['TSFE']->sys_language_content) {
				$row = $GLOBALS['TSFE']->sys_page->getRecordOverlay('tx_wseevents_events',
					$row, $GLOBALS['TSFE']->sys_language_content,
					$GLOBALS['TSFE']->sys_language_contentOL, '');
			}
			// Take the first event as selected if no event is selected in the URL
			if (0 == $showEvent) {
				$showEvent = $row['uid'];
			}
			$eventName = $row['name'];
			// Set one event option
			$markerArray['###VALUE###'] = $row['uid'];
			$markerArray['###OPTION###'] = $eventName;
			if ($showEvent==$row['uid']) {
				$event_item .= $this->cObj->substituteMarkerArrayCached($template['evtoptionsel'], $markerArray);
			} else {
				$event_item .= $this->cObj->substituteMarkerArrayCached($template['evtoption'], $markerArray);
			}
		}
		$GLOBALS['TYPO3_DB']->sql_free_result($res);

		// Show selection combo box if more than one event is found
		if (1 < $eventCount) {
			// Set select options
			$subPartArray1['###SELECT###'] = $event_item;
			// Set label for selection box
			$markerArray1['###LABEL###'] = $this->pi_getLL('tx_wseevents_sessions.chooseeventday', '[Choose event day]');
			//$markerArray1['###FORMACTION###'] = $this->pi_getPageLink($GLOBALS['TSFE']->page['uid']);
			$markerArray1['###FORMSELECT###'] = $this->prefixId . '[showEvent]';
			$markerArray1['###FORMSEND###'] = htmlspecialchars($this->pi_getLL('tx_wseevents_sessions.showselection', '[Show selection]'));
			$subPartArray['###EVENTSELECT###'] = $this->cObj->substituteMarkerArrayCached($template['evtsection'], $markerArray1, $subPartArray1);
		} else {
			$subPartArray['###EVENTSELECT###'] = '';
		}
		// show only sessions of selected event
//		if (0 < $showevent) {
//			$where .= ' AND event=' . $showevent;
//		}
		// Get event info
		$event = $this->events->getEventInfo($showEvent);

		// Create template data for eventday combobox
		$content_select = '';	// Clear var;
		if (0 == $useJQuery) {
			$markerArray['###VALUE###'] = 0;
			$markerArray['###OPTION###'] = $this->pi_getLL('tx_wseevents_sessions.choosealldays', '[-All-]');
			if (0 == $showDay) {
				$content_select .= $this->cObj->substituteMarkerArrayCached($template['optionsel'], $markerArray);
			} else {
				$content_select .= $this->cObj->substituteMarkerArrayCached($template['option'], $markerArray);
			}
		}

		// Get date format for selected language
		if (!$conf[$index . '.']['fmtDate']){
			$conf['strftime'] = '%d.%m.%Y';
		} else {
			$conf['strftime'] = $conf[$index . '.']['fmtDate'];
		}
		// Get count of days and name of days
		$secOfDay = 60 * 60 * 24;
		$dayCount = $event['length'];
		$dayName = array();
		$weekdays = array();
		for ( $d = 1; $d <= $dayCount; $d++ ) {
			$thisDay = $event['begin'] + ($d-1) * $secOfDay;
			$dayName[$d] = strftime($conf['strftime'], $thisDay);
			$weekdays[$d] = strftime('%A', $thisDay);

			// Set one event day option
			$markerArray['###VALUE###'] = $d;
			$markerArray['###OPTION###'] = $weekdays[$d] . ' - ' . $dayName[$d];
			if (($showDay==$d) or (1 == $useJQuery)) {
				if ( $d & 1 ) {
					$markerArray['###SELECTPOS###'] = 'left';
				} else {
					$markerArray['###SELECTPOS###'] = 'right';
				}
				$content_select .= $this->cObj->substituteMarkerArrayCached($template['optionsel'], $markerArray);
			} else {
				$content_select .= $this->cObj->substituteMarkerArrayCached($template['option'], $markerArray);
			}
		}

		// Create template data for rooms combobox
		$content_room_select = '';
		if (0 == $useJQuery) {
			$markerArray['###VALUE###'] = 0;
			$markerArray['###OPTION###'] = $this->pi_getLL('tx_wseevents_sessions.chooseallrooms', '[-All-]');
			if (0 == $showRoom) {
				$content_room_select .= $this->cObj->substituteMarkerArrayCached($template['roomoptionsel'], $markerArray);
			} else {
				$content_room_select .= $this->cObj->substituteMarkerArrayCached($template['roomoption'], $markerArray);
			}
		}

		// Get count of rooms and name of rooms
		if (1 == $showDebugSql) { echo 'getRoomInfo:' . $event['location'] . '<br>'; };
		$rooms = $this->getRoomInfo($event['location']);
		$roomCount = count($rooms);
		$roomIds = '';
		$roomName = array();
		for ( $r = 1; $r <= $roomCount; $r++ ) {
			$roomName[$r] = $rooms[$r]['name'];
			if (empty($roomIds)) {
				$roomIds = $rooms[$r]['uid'];
			} else {
				$roomIds .= ',' . $rooms[$r]['uid'];
			}
			// Set one event room option
			$markerArray['###VALUE###'] = $r;
			$markerArray['###OPTION###'] = $roomName[$r];
			if (($showRoom==$r) or (1 == $useJQuery)) {
				$content_room_select .= $this->cObj->substituteMarkerArrayCached($template['roomoptionsel'], $markerArray);
			} else {
				$content_room_select .= $this->cObj->substituteMarkerArrayCached($template['roomoption'], $markerArray);
			}
		}
		// Create a list with the times of slot begins
		// Get begin of slots
		$timeBegin = $event['timebegin'];
		$t_start = strtotime($timeBegin);
		// Get end of slots
		$timeEnd   = $event['timeend'];
		$t_end = strtotime($timeEnd);
		// Get count of slots
		$slotLen = $event['slotsize']*60;
		$slotCount = ($t_end - $t_start)/$slotLen;
		$slotName = array();
		$slotBegin = array();
		$slotEnding = array();
		for ( $s = 1; $s <= $slotCount+1; $s++ ) {
			$slotName[$s] = 'Slot ' . $s;
			$slotBegin[$s] = date('H:i', (($s-1)*$slotLen+$t_start));
			$slotEnding[$s] = date('H:i', (($s)*$slotLen+$t_start));
			// %H:%M
		}

		// Create template data for category combobox
		$content_category_select = '';
		if (0 == $useJQuery) {
			$markerArray['###VALUE###'] = 0;
			$markerArray['###OPTION###'] = $this->pi_getLL('tx_wseevents_sessions.chooseallcategories', '[-All-]');
			if (0 == $showCategory) {
				$content_category_select .= $this->cObj->substituteMarkerArrayCached($template['catoptionsel'], $markerArray);
			} else {
				$content_category_select .= $this->cObj->substituteMarkerArrayCached($template['catoption'], $markerArray);
			}
		}
		if (1 == $showCategorySelector) {
			$allCategories = $this->getCategoryInfo($showEvent);
			foreach ($allCategories as $thisCategory) {
				// Set one event category option
				if (0 == $useJQuery) {
					$markerArray['###VALUE###'] = $thisCategory['shortkey'];
				} else {
					$markerArray['###VALUE###'] = $thisCategory['uid'];
				}
				$markerArray['###OPTION###'] = $thisCategory['name'];
				$markerArray['###COLOR###'] = $thisCategory['color'];
				if (($showCategory == $thisCategory['shortkey']) or (1 == $useJQuery)) {
					$content_category_select .= $this->cObj->substituteMarkerArrayCached($template['catoptionsel'], $markerArray);
				} else {
					$content_category_select .= $this->cObj->substituteMarkerArrayCached($template['catoption'], $markerArray);
				}
			}
		}

		// show time selector
		$slotStart = 1;
		$slotEnd = $slotCount;
		if (!empty($showBegin)) {
			$slotStart = $showBegin;
		}
		if (!empty($showEnd)) {
			$slotEnd = $showEnd;
		}
		$content_time_start_select = '';
		$content_time_end_select = '';
		$content_time_select = '';
		if (1 == $showHourSelector) {
			if (1 == $useJQuery) {
				$template['timeselect']			= $this->cObj->getSubpart($template['total'],		'###TIMESELECTJQUERY###');
				$template['timestartselect']	= '';
				$template['timeendselect']		= '';
				$templateSelect					= $this->cObj->getSubpart($template['timeselect'],	'###SELECT###');
//				$templateOption					= $this->cObj->getSubpart($templateSelect,			'###OPTIONNOTSELECTED###');
				$templateOptionSelected			= $this->cObj->getSubpart($templateSelect,			'###OPTIONSELECTED###');
				if (count($timeSelect) > 0) {
					foreach ($timeSelect as $timeRange) {
						$markerArray['###VALUE###'] = $timeRange['Slots'];
						$markerArray['###OPTION###'] = $timeRange['Name'];
						$content_time_select .= $this->cObj->substituteMarkerArrayCached($templateOptionSelected, $markerArray);
					}
				}
			} else {
				$template['timeselect']			= '';
				$template['timestartselect']	= $this->cObj->getSubpart($template['total'],		'###TIMESTARTSELECT###');
				$template['timeendselect']		= $this->cObj->getSubpart($template['total'],		'###TIMEENDSELECT###');
				$templateSelect					= $this->cObj->getSubpart($template['timestartselect'],	'###SELECT###');
				$templateOption					= $this->cObj->getSubpart($templateSelect,			'###OPTIONNOTSELECTED###');
				$templateOptionSelected			= $this->cObj->getSubpart($templateSelect,			'###OPTIONSELECTED###');
				for ( $s = 1; $s <= $slotCount; $s++ ) {
					$markerArray['###VALUE###'] = $s;
					$markerArray['###OPTION###'] = $slotBegin[$s];
					if ($slotStart == $s) {
						$content_time_start_select .= $this->cObj->substituteMarkerArrayCached($templateOptionSelected, $markerArray);
					} else {
						$content_time_start_select .= $this->cObj->substituteMarkerArrayCached($templateOption, $markerArray);
					}
					$markerArray['###OPTION###'] = $slotEnding[$s];
					if ($slotEnd == $s) {
						$content_time_end_select .= $this->cObj->substituteMarkerArrayCached($templateOptionSelected, $markerArray);
					} else {
						$content_time_end_select .= $this->cObj->substituteMarkerArrayCached($templateOption, $markerArray);
					}
				}
			}
		}

		// Calculate column width if enabled
		$slotColWidth = 10;
		if (0 < $timeColWidth) {
			if (0 == $showDay) {
				$columnCount = $dayCount * $roomCount;
			} else {
				$columnCount = $roomCount;
			}
			if (0 == $columnCount) {
				$columnCount = 1;
			}
			$slotColWidth = (100 - $timeColWidth - (($dayCount-1) * $dayDelimiterWidth)) / $columnCount;
		}

		if ($showDay > 0) {
			$showDaysVertical = 0;
		}
		// Loop over all days vertical
		if ((1 == $showDaysVertical) and (0 == $showDay)) {
			$showDay = 1;
		}
		while ($showDay <= $dayCount) {

			// Here the output begins
			$content_title = '';
			$content_header = '';
			$content_slot = '';
			$visible = array();

			// Loop over all days
			for ( $d = 1; $d <= $dayCount; $d++ ) {
				$roomTime = $roomTimeSetting;
				if (($showDay == $d) or (0 == $showDay)) {
					// Loop over all rooms
					$newRoomCount = 0;
					for ( $r = 1; $r <= $roomCount; $r++ ) {
						if (($showRoom == $r) or (0 == $showRoom)) {
							if (1 == $hideEmptyRooms) {
								$visible[$d][$r] = $this->checkRoom($showEvent, $d, $rooms[$r]['uid'], $showDebugSql);
							} else {
								$visible[$d][$r] = 1;
							}
							if (0 < $visible[$d][$r]) {
								$newRoomCount += 1;
								$markerArray = array();
								$markerArray['###DAYNR###'] = $d;
								$markerArray['###ROOMNR###'] = $r;
								$markerArray['###HEADERROOM###'] = $roomName[$r];
								// Add column width if enabled
								if ($timeColWidth>0) {
									$markerArray['###COLUMNWIDTH###']  = $slotColWidth . '%';
								}
								$content_header .= $this->cObj->substituteMarkerArrayCached($template['headercol'], $markerArray);
							}
						} else {
							$visible[$d][$r] = 0;
						}
					}

					$markerArray = array();
					$markerArray['###DAYNR###'] = $d;
					$markerArray['###ROOMNR###'] = $r;
					$markerArray['###ROOMCOUNT###'] = $newRoomCount;
					$markerArray['###TITLEDAY###'] = $dayName[$d];
					$markerArray['###TITLEWEEKDAY###'] = $weekdays[$d];
					// Add column width if enabled
					if (0 < $timeColWidth) {
						$markerArray['###COLUMNWIDTH###'] = ($slotColWidth * $newRoomCount) . '%';
					}
					$content_title .= $this->cObj->substituteMarkerArrayCached($template['titlecol'], $markerArray);

					// Insert space between days if defined
					if ((0 == $showDay) and ($d<$dayCount)) {
						if (0 < $dayDelimiterWidth) {
							$markerArray = array();
							$markerArray['###DAYNR###'] = $d;
							$markerArray['###ROOMNR###'] = $r;
							$markerArray['###COLUMNWIDTH###'] = $dayDelimiterWidth . '%';
							$markerArray['###DAYDELIMITER###'] = $dayDelimiterClass;
							$content_title .= $this->cObj->substituteMarkerArrayCached($template['headercolempty'], $markerArray);
							$content_header .= $this->cObj->substituteMarkerArrayCached($template['headercolempty'], $markerArray);
						}
					}
				}
			}

			// Loop over all slots of a day
			for ( $s = $slotStart; $s <= $slotEnd; $s++ ) {
				$content_slotRow = '';
				// Loop over all days
				for ( $d = 1; $d <= $dayCount; $d++ ) {
					if (($showDay==$d) or (0 == $showDay)) {
						// Loop over all rooms
						$allRooms = false;
						for ( $r = 1; $r <= $roomCount; $r++ ) {
							if (0 < $visible[$d][$r]) {
								if (0 < $showDebug) {
									$content_slotRow .= LF . '<!-- s=' . $s . ' d=' . $d . ' r=' . $r . ' -->';
								}
								if (1 == $showDebugSql) { echo '<br>getSlot:' . $showEvent . ', ' . $d . ', ' . $rooms[$r]['uid'] . ', ' . $s . '<br>'; };
								$slot_id = $this->eventTimeSlots->getSlot($showEvent, $d, $rooms[$r]['uid'], $s, $showDebugSql);
								if (1 == $r && empty($slot_id) && !$allRooms) {
									// Check if a slot is assigned for all rooms
									if (1 == $showDebugSql) { echo 'getSlot:' . $showEvent . ', ' . $d . ', 0, ' . $s . '<br>'; };
									$slot_id = $this->eventTimeSlots->getSlot($showEvent, $d, 0, $s, $showDebugSql);
									if (!empty($slot_id)) {
										$allRooms = true;
									}
								}
								$slot_len = 1;
								if (!empty($slot_id)) {
									if (1 == $showDebugSql) { echo 'getSlotLength:' . $slot_id . '<br>'; };
									$slot_len = $this->eventTimeSlots->getSlotLength($slot_id);
									if (1 == $showDebugSql) { echo 'slot_len:' . $slot_len . '<br>getSlotSession:' . $slot_id . '<br>'; };
									$sessionData = $this->eventTimeSlots->getSlotSession($showEvent, $slot_id);
									if (1 == $showDebugSql) { echo 'sessiondata:' . $sessionData . '<br>'; };
									if (!empty($sessionData)) {
										if ((!empty($showCategory) and ($showCategory != $sessionData['catkey']))) {
											$slot_id = '';
										}
									}
								}
								if (!empty($slot_id)) {
									if (!empty($sessionData)) {
										$label = $sessionData['catnum'];  // the link text
										//$overrulePiVars = '';//array('session' => $this->getFieldContent('uid'));
										$overrulePiVars = array('showSessionUid' => $sessionData['uid'], 'backUid' => $GLOBALS['TSFE']->id);
										$clearAnyway = 1;    // the current values of piVars will NOT be preserved
										$altPageId = $this->conf['singleSession'];      // ID of the target page, if not on the same page
										$this->setCache();
										if (!t3lib_div::inList($hideCat, $sessionData['catkey'])) {
											$sessionLink = $this->pi_linkTP_keepPIvars($label, $overrulePiVars,
												$this->useCache, $clearAnyway, $altPageId);
										} else {
											$sessionLink = '';
										}
										$label = $sessionData['name'];  // the link text
										$sessionLinkName = $this->pi_linkTP_keepPIvars($label, $overrulePiVars,
											$this->useCache, $clearAnyway, $altPageId);
										if (1 == $this->conf['showCalendarLink']) {
											// Create link for iCal download
											$overrulePiVars = array('sessionUid' => $sessionData['uid'], 'slotUid' => $slot_id, 'download' => 'iCal');
											if (!empty($this->conf['calendarLinkLabel'])) {
												$label = $this->conf['calendarLinkLabel'];
											} else {
												$label = 'iCal';  // the link text
											}
											$iCalLinkName = $this->pi_linkTP_keepPIvars($label, $overrulePiVars,
												$this->useCache, $clearAnyway, $altPageId);
										} else {
											$iCalLinkName = '';
										}
										$markerArray = array();
										$markerArray['###SLOTNAME###'] = $sessionData['name'];
										$markerArray['###SLOTCATEGORY###'] = $sessionData['category'];
										$markerArray['###SLOTCATEGORYKEY###'] = $sessionData['catkey'];
										$markerArray['###SLOTCATEGORYCOLOR###'] = $sessionData['catcolor'];
										$markerArray['###SLOTICAL###'] = $iCalLinkName;
										$markerArray['###SLOTLINK###'] = $sessionLink;
										$markerArray['###SLOTLINKNAME###'] = $sessionLinkName;
										$markerArray['###SLOTSESSION###'] = $sessionData['catnum'];
										// Cut teaser if longer than max teaser width
										if (0 < $teaserWidth) {
											$markerArray['###SLOTTEASER###'] = $GLOBALS['TSFE']->csConvObj->crop(
																				$GLOBALS['TSFE']->renderCharset,
																				$sessionData['teaser'],
																				$teaserWidth, '...');
										} else {
											$markerArray['###SLOTTEASER###'] = $sessionData['teaser'];
										}
										// ToDo: Ticket #11, add variable SPEAKERDATA to SLOTSALL
										// http://trac.netlabs.org/wse_events/ticket/11

										// Get speaker list of session
										$markerArray['###SLOTSPEAKER###'] = $this->getSpeakerNames($sessionData['speaker']);
									} else {
										$markerArray = array();
										if (0 == $hideNotAssigned) {
											$markerArray['###SLOTNAME###'] = $this->pi_getLL('tx_wseevents_sessions.slot_notassigned');
											$markerArray['###SLOTSESSION###'] = $this->pi_getLL('tx_wseevents_sessions.slot_notassigned');
											$markerArray['###SLOTTEASER###'] = $this->pi_getLL('tx_wseevents_sessions.slot_notassigned');
											$markerArray['###SLOTSPEAKER###'] = '';
										} else {
											$markerArray['###SLOTNAME###'] = '';
											$markerArray['###SLOTSESSION###'] = '';
											$markerArray['###SLOTTEASER###'] = '';
											$markerArray['###SLOTSPEAKER###'] = '';
										}
										$markerArray['###SLOTCATEGORY###'] = 0;
										$markerArray['###SLOTCATEGORYKEY###'] = $this->pi_getLL('tx_wseevents_sessions.slot_notassigned_catkey');
										$markerArray['###SLOTCATEGORYCOLOR###'] = $catColorNotAssigned;
										$markerArray['###SLOTICAL###'] = '';
										$markerArray['###SLOTLINK###'] = '';
										$markerArray['###SLOTLINKNAME###'] = '';
									}
									$markerArray['###DAYNR###'] = $d;
									$markerArray['###ROOMNR###'] = $r;
									$markerArray['###SLOTNR###'] = $s;
									$markerArray['###SLOTDAY###'] = $d;
									$markerArray['###SLOTROOM###'] = $r;
									$markerArray['###SLOTNUM###'] = $s;
									$markerArray['###SLOTBEGIN###'] = $slotBegin[$s];
									$markerArray['###SLOTEND###'] = $slotBegin[$s + $slot_len];
									$markerArray['###SLOTSIZE###'] = $slot_len;
									if ($allRooms) {
										$slotWidth = $roomCount;
									} else {
										$slotWidth = 1;
									}
									$markerArray['###SLOTWIDTH###'] = $slotWidth;
									$markerArray['###DAYDELIMITER###']  = '';
									// Add column width if enabled
									if (0 < $timeColWidth) {
										$markerArray['###COLUMNWIDTH###']  = ($slotColWidth * $slotWidth) . '%';
									}
									$content_slotRow .= $this->cObj->substituteMarkerArrayCached($template['slotcol'], $markerArray);
									for ( $x = $s; $x < $s+$slot_len; $x++) {
										if ($allRooms) {
											for ( $r1 = 1; $r1 <= $roomCount; $r1++ ) {
												$used[$x][$d][$r1] = ($x==$s) ? $slot_len : (-$slot_len);
											}
										} else {
											$used[$x][$d][$r] = ($x==$s) ? $slot_len : (-$slot_len);
										}
									}
								} else {
									if (empty($used[$s][$d][$r])) {
										$markerArray = array();
										$markerArray['###DAYNR###'] = $d;
										$markerArray['###ROOMNR###'] = $r;
										$markerArray['###SLOTNR###'] = $s;
										$markerArray['###SLOTDAY###'] = $d;
										$markerArray['###SLOTROOM###'] = $r;
										$markerArray['###SLOTNUM###'] = $s;
										$markerArray['###SLOTBEGIN###'] = $slotBegin[$s];
										$markerArray['###SLOTEND###'] = $slotBegin[$s+1];
										$markerArray['###SLOTSIZE###'] = 1;
										$markerArray['###SLOTWIDTH###'] = 1;
										if (0 == $hideNotDefined) {
											$markerArray['###SLOTNAME###'] = $this->pi_getLL('tx_wseevents_sessions.slot_notdefined');
											$markerArray['###SLOTSESSION###'] = $this->pi_getLL('tx_wseevents_sessions.slot_notdefined');
											$markerArray['###SLOTTEASER###'] = $this->pi_getLL('tx_wseevents_sessions.slot_notdefined');
										} else {
											$markerArray['###SLOTNAME###'] = '';
											$markerArray['###SLOTSESSION###'] = '';
											$markerArray['###SLOTTEASER###'] = '';
										}
										$markerArray['###SLOTCATEGORY###'] = 0;
										$markerArray['###SLOTCATEGORYKEY###'] = $this->pi_getLL('tx_wseevents_sessions.slot_notdefined_catkey');
										$markerArray['###SLOTCATEGORYCOLOR###'] = $catColorNotDefined;
										$markerArray['###SLOTLINK###'] = '';
										$markerArray['###DAYDELIMITER###']  = '';
										// Add column width if enabled
										if ($timeColWidth>0) {
											$markerArray['###COLUMNWIDTH###']  = $slotColWidth . '%';
										}
										$content_slotRow .= $this->cObj->substituteMarkerArrayCached($template['slotcolempty'], $markerArray);
									}
								}
							}
						}
						if ((0 == $showDay) and ($d < $dayCount)) {
							if (0 < $dayDelimiterWidth) {
								$markerArray = array();
								$markerArray['###DAYNR###'] = $d;
								$markerArray['###ROOMNR###'] = 0;
								$markerArray['###SLOTNR###'] = $s;
	/*
								$markerArray['###SLOTDAY###'] = '';
								$markerArray['###SLOTROOM###'] = '';
								$markerArray['###SLOTNUM###'] = '';
								$markerArray['###SLOTBEGIN###'] = '';
								$markerArray['###SLOTEND###'] = '';
								$markerArray['###SLOTNAME###'] = '';
								$markerArray['###SLOTSESSION###'] = '';
								$markerArray['###SLOTTEASER###'] = '';
								$markerArray['###SLOTCATEGORYKEY###'] = '';
								$markerArray['###SLOTCATEGORYCOLOR###'] = $catcolor_notdefined;
								$markerArray['###SLOTLINK###'] = '';
	*/
								$markerArray['###SLOTCATEGORY###'] = 0;
								$markerArray['###SLOTSIZE###'] = 1;
								$markerArray['###SLOTWIDTH###'] = 1;
								$markerArray['###COLUMNWIDTH###'] = $dayDelimiterWidth . '%';
								$markerArray['###DAYDELIMITER###'] = $dayDelimiterClass;
								$content_slotRow .= $this->cObj->substituteMarkerArrayCached($template['slotcolempty'], $markerArray);
							}
						}
					}
				}
				$subPartArray1['###SLOTCOLUMN###'] = $content_slotRow;
				$subPartArray1['###SLOTCOLUMNEMPTY###'] = '';
				// Column with Start and end time
				$markerArray = array();
				$content_timeCol = '';
				$content_timeColFree = '';
				if (0 == $roomTime) {
					$markerArray['###SLOTBEGIN###'] = $slotBegin[$s];
					$markerArray['###SLOTEND###']   = $slotBegin[$s+1];
					$markerArray['###SLOTSIZE###']  = 1;
					// Add column width if enabled
					if (0 < $timeColWidth) {
						$markerArray['###COLUMNWIDTH###']  = $timeColWidth . '%';
					}
					if (1 == $showDaysVertical) {
						$markerArray['###DAYNR###'] = $showDay;
					} else {
						$markerArray['###DAYNR###'] = 0;
					}
					$content_timeCol = $this->cObj->substituteMarkerArrayCached($template['timecol'], $markerArray);
				} else {
					if (0 < $showRoom) {
						$roomTime = $showRoom;
					}
					$timeDay = 1;
					if (0 < $showDay) {
						$timeDay = $showDay;
					} else {
						for ( $i=1; $i<=$dayCount; $i++ ) {
							if (!empty($used[$s][$i][$roomTime])) {
								$timeDay = $i;
							}
						}
					}
					if (empty($used[$s][$timeDay][$roomTime])) {
						for ( $i=1; $i<=2; $i++ ) {
							if (!empty($used[$s][$timeDay][$i])) {
								$roomTime = $i;
							}
						}
					}
					if (!empty($used[$s][$timeDay][$roomTime])) {
						$slot_len = $used[$s][$timeDay][$roomTime];
						if (0 < $slot_len) {
							$markerArray['###SLOTBEGIN###'] = $slotBegin[$s];
							$markerArray['###SLOTEND###']   = $slotBegin[$s+$slot_len];
							$markerArray['###SLOTSIZE###']  = $slot_len;
							// Add column width if enabled
							if (0 < $timeColWidth) {
								$markerArray['###COLUMNWIDTH###']  = $timeColWidth . '%';
							}
							if (1 == $showDaysVertical) {
								$markerArray['###DAYNR###'] = $showDay;
							} else {
								$markerArray['###DAYNR###'] = 0;
							}
							$content_timeCol = $this->cObj->substituteMarkerArrayCached($template['timecol'], $markerArray);
						}
					} else {
						$markerArray['###SLOTBEGIN###'] = $slotBegin[$s];
						$markerArray['###SLOTEND###']   = $slotBegin[$s+1];
						$markerArray['###SLOTSIZE###']  = 1;
						// Add column width if enabled
						if (0 < $timeColWidth) {
							$markerArray['###COLUMNWIDTH###']  = $timeColWidth . '%';
						}
						if (1 == $showDaysVertical) {
							$markerArray['###DAYNR###'] = $showDay;
						} else {
							$markerArray['###DAYNR###'] = 0;
						}
						$content_timeColFree = $this->cObj->substituteMarkerArrayCached($template['timecolfree'], $markerArray);
					}
				}
				// Add debug output if enabled
				if (0 < $showDebug) {
					$content_timeCol = LF . '<!-- s=' . $s . ' d=' . $d . ' timecol -->' . $content_timeCol;
					$content_timeColFree = LF . '<!-- s=' . $s . ' d=' . $d . ' timecolfree -->' . $content_timeColFree;
				}
				$subPartArray1['###TIMECOLUMN###'] = $content_timeCol;
				$subPartArray1['###TIMECOLUMNEMPTY###'] = $content_timeColFree;

				$markerArray['###SLOTNR###'] = $s;
				$content_slot .= $this->cObj->substituteMarkerArrayCached($template['slotrow'], $markerArray, $subPartArray1);
			}

			$subPartArray['###SLOTROW###']  = $content_slot;

			$subPartArray1['###HEADERCOLUMN###'] = $content_header;
			$subPartArray1['###HEADERCOLUMNEMPTY###'] = '';
			$markerArray = array();
			if (0 == $hideTime) {
				$markerArray['###HEADERBEGIN###'] = $this->pi_getLL('tx_wseevents_sessions.slot_titlebegin', 'Time');
			} else {
				$markerArray['###HEADERBEGIN###'] = '';
			}
			// Add column width if enabled
			if (0 < $timeColWidth) {
				$markerArray['###COLUMNWIDTH###']  = $timeColWidth . '%';
			}
			$subPartArray['###HEADERROW###']  = $this->cObj->substituteMarkerArrayCached($template['headerrow'], $markerArray, $subPartArray1);

			$subPartArray1['###TITLECOLUMN###'] = $content_title;
			$subPartArray1['###SELECT###'] = $content_select;
			$markerArray = array();
			$markerArray['###TITLEBEGIN###'] = '';
			// Add column width if enabled
			if (0 < $timeColWidth) {
				$markerArray['###COLUMNWIDTH###']  = $timeColWidth . '%';
			}
			$markerArray['###LABEL###'] = $this->pi_getLL('tx_wseevents_sessions.chooseeventday', '[Choose event day]');
			$markerArray['###FORMACTION###'] = $this->pi_getPageLink($GLOBALS['TSFE']->page['uid']);
			$markerArray['###FORMSELECT###'] = $this->prefixId . '[showDay]';
			$markerArray['###FORMSEND###'] = htmlspecialchars($this->pi_getLL('tx_wseevents_sessions.showselection', '[Show selection]'));
			if (empty($content)) {
				if (1 == $useJQuery) {
					$subPartArray['###TITLEDAYSELECT###'] = '';
					$subPartArray['###TITLEDAYSELECTJQUERY###'] = $this->cObj->substituteMarkerArrayCached($template['titledayselect'], $markerArray, $subPartArray1);
				} else {
					$subPartArray['###TITLEDAYSELECT###'] = $this->cObj->substituteMarkerArrayCached($template['titledayselect'], $markerArray, $subPartArray1);
					$subPartArray['###TITLEDAYSELECTJQUERY###'] = '';
				}
			} else {
				$subPartArray['###TITLEDAYSELECT###'] = '';
				$subPartArray['###TITLEDAYSELECTJQUERY###'] = '';
			}
			$subPartArray['###TITLEROW###']  = $this->cObj->substituteMarkerArrayCached($template['titlerow'], $markerArray, $subPartArray1);

			if ((empty($content)) and (1 == $showRoomSelector)) {
				$markerArray['###FORMSELECT###'] = $this->prefixId . '[showRoom]';
				$subPartArray1['###SELECT###'] = $content_room_select;
				if (1 == $useJQuery) {
					$subPartArray['###TITLEROOMSELECTJQUERY###'] = $this->cObj->substituteMarkerArrayCached($template['titleroomselect'], $markerArray, $subPartArray1);
					$subPartArray['###TITLEROOMSELECT###'] =  '';
				} else {
					$subPartArray['###TITLEROOMSELECTJQUERY###'] = '';
					$subPartArray['###TITLEROOMSELECT###'] = $this->cObj->substituteMarkerArrayCached($template['titleroomselect'], $markerArray, $subPartArray1);
				}
			} else {
				$subPartArray['###TITLEROOMSELECT###'] = '';
				$subPartArray['###TITLEROOMSELECTJQUERY###'] = '';
			}

			if ((empty($content)) and (1 == $showCategorySelector)) {
				$markerArray['###FORMSELECT###'] = $this->prefixId . '[showCategory]';
				$subPartArray1['###SELECT###'] = $content_category_select;
				if (1 == $useJQuery) {
					$subPartArray['###CATEGORYSELECTJQUERY###'] = $this->cObj->substituteMarkerArrayCached($template['catsection'], $markerArray, $subPartArray1);
					$subPartArray['###CATEGORYSELECT###'] = '';
				} else {
					$subPartArray['###CATEGORYSELECTJQUERY###'] = '';
					$subPartArray['###CATEGORYSELECT###'] = $this->cObj->substituteMarkerArrayCached($template['catsection'], $markerArray, $subPartArray1);
				}
			} else {
				$subPartArray['###CATEGORYSELECT###'] = '';
				$subPartArray['###CATEGORYSELECTJQUERY###'] = '';
			}

			if ((empty($content)) and (1 == $showHourSelector)) {
				$markerArray['###FORMSELECT###'] = '';
				$subPartArray1['###SELECT###'] = $content_time_select;
				$subPartArray['###TIMESELECTJQUERY###'] = $this->cObj->substituteMarkerArrayCached($template['timeselect'], $markerArray, $subPartArray1);
				$markerArray['###FORMSELECT###'] = $this->prefixId . '[showBegin]';
				$subPartArray1['###SELECT###'] = $content_time_start_select;
				$subPartArray['###TIMESTARTSELECT###'] = $this->cObj->substituteMarkerArrayCached($template['timestartselect'], $markerArray, $subPartArray1);
				$markerArray['###FORMSELECT###'] = $this->prefixId . '[showEnd]';
				$subPartArray1['###SELECT###'] = $content_time_end_select;
				$subPartArray['###TIMEENDSELECT###'] = $this->cObj->substituteMarkerArrayCached($template['timeendselect'], $markerArray, $subPartArray1);
				$markerArray['###HIDEDAYS###'] = '';
				// Check the actual date and time and show only sessions after this time during an event
				list($thisDayNr, $thisSlotNr) = $this->events->checkForToday($showEvent);
				$markerArray['###HIDEDAYS###'] .= '$(".tx-wseevents-pi1-choosetimeday input").each(function( index ) {
					var dayClass = ".daynr-" + $(this).val();
					if ($(this).val() == "' . $thisDayNr .  '") {
						$(this).prop( "checked", true );
					} else {
						$(this).prop( "checked", false );
					}
					var check = $(this).attr("checked");
					if ("checked" == check) {
						$(dayClass).removeClass("tx-wseevents-pi1-hide");
					} else {
						$(dayClass).addClass("tx-wseevents-pi1-hide");
					}
				});' . LF;
				$markerArray['###HIDEDAYS###'] .= '$(".tx-wseevents-pi1-choosetime input").each(function( index ) {
					var slotlist = $(this).val();
					var slots = new Array();
					slots = slotlist.split(",");
					var checkVal = false;
					for (var i = 0; i < slots.length; i++) {
						if (slots[i] >= ' . $thisSlotNr . ') {
							checkVal = true;
						}
					}
					$(this).prop( "checked", checkVal );
					var check = $(this).attr("checked");
					for (var i = 0; i < slots.length; i++) {
						var timeClass = ".slotnr-" + slots[i];
						if ("checked" == check) {
							$(timeClass).removeClass("tx-wseevents-pi1-hide");
						} else {
							$(timeClass).addClass("tx-wseevents-pi1-hide");
						}
					}
				});' . LF;
				$markerArray['###RESTRICT_SELECTION###'] = $this->pi_getLL('tx_wseevents_sessions.slot_choose_select', 'Restrict selection');
				$subPartArray['###SELECTDETAIL###'] = $this->cObj->substituteMarkerArrayCached($template['selectdetail'], $markerArray, $subPartArray1);
			} else {
				$subPartArray['###TIMESELECTJQUERY###'] = '';
				$subPartArray['###TIMESTARTSELECT###'] = '';
				$subPartArray['###TIMEENDSELECT###'] = '';
				$subPartArray['###SELECTDETAIL###'] = '';
			}

			// ToDo: At this point the selection (combo) box must be put into the template.

			$markerArray = array();
			if (1 == $showDaysVertical) {
				$markerArray['###DAYNR###'] = $showDay;
				$markerArray['###DAYNR1###'] = $showDay - 1;
			} else {
				$markerArray['###DAYNR###'] = 0;
				$markerArray['###DAYNR1###'] = 0;
			}
			$content .= $this->cObj->substituteMarkerArrayCached($template['total'], $markerArray, $subPartArray);

			if ((0 == $showDay) or (0 == $showDaysVertical)) {
				$showDay = $dayCount;
			}
			$showDay++;
		}
		return $content;
	}









	/**
	 * Display the details of a single session
	 *
	 * @param	string		$content default content string, ignore
	 * @param	array		$conf TypoScript configuration for the plugin
	 * @return	string		Content for output on the web site
	 */
	function singleSessionView($content, $conf)	{
		$this->conf=$conf;
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();

		if (isset($this->piVars['showSessionUid'])) {
			$this->internal['currentRow'] = $this->pi_getRecord($this->internal['currentTable'],
				$this->piVars['showSessionUid']);
		}

		// Check if template file is set, if not use the default template
		if (!isset($conf['templateFile'])) {
			$templateFile = 'EXT:wse_events/wseevents.tmpl';
		} else {
			$templateFile = $conf['templateFile'];
		}
		// Get the template
		$this->templateCode = $this->cObj->fileResource($templateFile);

		// Get the parts out of the template
		$template['total'] = $this->cObj->getSubpart($this->templateCode, '###SESSIONVIEW###');

		// This sets the title of the page for use in indexed search results:
		if ($this->internal['currentRow']['title'])	$GLOBALS['TSFE']->indexedDocTitle=$this->internal['currentRow']['title'];

		// Check if target for documents link is set, if not use the default target
		if (!isset($conf['documentsTarget'])) {
			$this->documentsTarget = 'target="_blank"';
		} else {
			$this->documentsTarget = $conf['documentsTarget'];
		}
		// Check for delimiter between the documents
		if (!isset($conf['documentsdelimiter'])) {
			$this->internal['documentsdelimiter'] = '<br />';
		} else {
			$this->internal['documentsdelimiter'] = $conf['documentsdelimiter'];
		}

		// Link for back to list view
		$label = $this->pi_getLL('back', 'Back');	// the link text
		$overrulePiVars = array ();
		$clearAnyway = 1;							// the current values of piVars will NOT be preserved
		$altPageId = $this->piVars['backUid'];		// ID of the view page
		$this->setCache();
		if (0 < $altPageId) {
			$backLink = $this->pi_linkTP_keepPIvars($label, $overrulePiVars, $this->useCache, $clearAnyway, $altPageId);
		} else {
			$backLink = '';
		}

		$markerArray['###SESSIONNAME###'] = $this->getFieldContent('name');
		$markerArray['###SESSIONNUMBER###'] = $this->getFieldContent('number');

		$dataCat = $this->pi_getRecord('tx_wseevents_categories', $this->internal['currentRow']['category']);
		$markerArray['###SESSIONCATEGORY###'] = $this->internal['currentRow']['category'];
		$markerArray['###SESSIONCATEGORYKEY###'] = $dataCat['shortkey'];
		$markerArray['###SESSIONCATEGORYCOLOR###'] = $dataCat['color'];

		$markerArray['###SESSIONTEASERNAME###'] = $this->getFieldHeader('teaser');
		$markerArray['###SESSIONTEASER###'] = $this->getFieldContent('teaser');
		$markerArray['###SPEAKERNAME###'] = $this->getFieldHeader('speaker');
		$markerArray['###SPEAKERDATA###'] = $this->getFieldContent('speaker');
		$markerArray['###TIMESLOTSNAME###'] = $this->getFieldHeader('timeslots');
		$markerArray['###TIMESLOTSDATA###'] = $this->getFieldContent('timeslots');
		$markerArray['###SESSIONDESCRIPTIONNAME###'] = $this->getFieldHeader('description');
		$markerArray['###SESSIONDESCRIPTION###'] = $this->getFieldContent('description');
		$markerArray['###SESSIONDOCUMENTSNAME###'] = $this->getFieldHeader('documents');
		$markerArray['###SESSIONDOCUMENTS###'] = $this->getFieldContent('documents');
		$markerArray['###BACKLINK###'] = $backLink;

//		$this->pi_getEditPanel();

		return $this->cObj->substituteMarkerArrayCached($template['total'], $markerArray);
	}









	/**
	 * Display the details of a single speaker
	 *
	 * @param	string		$content default content string, ignore
	 * @param	array		$conf TypoScript configuration for the plugin
	 * @return	string		Content for output on the web site
	 */
	function singleSpeakerView($content, $conf)	{
		$this->conf=$conf;
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();

		if (isset($this->piVars['showSpeakerUid'])) {
			$this->internal['currentRow'] = $this->pi_getRecord($this->internal['currentTable'],
				$this->piVars['showSpeakerUid']);
			// ToDo: t3lib_pageSelect::getRecordOverlay
		}

		// Check if upload directory is set, if not use the default directory
		if (!isset($conf['uploadDirectory'])) {
			$uploadDirectory = 'uploads/tx_wseevents';
		} else {
			$uploadDirectory = $conf['uploadDirectory'];
		}

		// Check if template file is set, if not use the default template
		if (!isset($conf['templateFile'])) {
			$templateFile = 'EXT:wse_events/wseevents.tmpl';
		} else {
			$templateFile = $conf['templateFile'];
		}
		// Get the template
		$this->templateCode = $this->cObj->fileResource($templateFile);

		// Get the parts out of the template
		$template['total'] = $this->cObj->getSubpart($this->templateCode, '###SPEAKERVIEW###');
		$template['sessionrow'] = $this->cObj->getSubpart($template['total'], '###SESSIONROW###');

		// This sets the title of the page for use in indexed search results:
		if ($this->internal['currentRow']['title'])	$GLOBALS['TSFE']->indexedDocTitle=$this->internal['currentRow']['title'];

		// Link for back to list view
		$label = $this->pi_getLL('back', 'Back');	// the link text
		$overrulePiVars = array ();
		$clearAnyway = 1;							// the current values of piVars will NOT be preserved
		$altPageId = $this->piVars['backUid'];		// ID of the view page
		$this->setCache();
		if (0 < $altPageId) {
			$backLink = $this->pi_linkTP_keepPIvars($label, $overrulePiVars, $this->useCache, $clearAnyway, $altPageId);
		} else {
			$backLink = '';
		}

		// Check if the speaker has a session on this event
		if (isset($this->piVars['showSpeakerUid'])) {
			$sessionIds = $this->getSpeakerSessionList($this->piVars['showSpeakerUid'], $this->conf['pidListEvents']);
		} else {
			$sessionIds = '';
		}

		$markerArray['###SPEAKERNAME###']	= $this->getFieldContent('name');
		$markerArray['###EMAILNAME###']		= $this->getFieldHeader('email');
		$markerArray['###EMAILDATA###']		= $this->getFieldContent('email');
		$markerArray['###COUNTRYNAME###']	= $this->getFieldHeader('country');
		$markerArray['###COUNTRYDATA###']	= $this->getFieldContent('country');
		$markerArray['###COMPANYNAME###']	= $this->getFieldHeader('company');
		$markerArray['###COMPANYDATA###']	= $this->getFieldContent('company');
		$markerArray['###COMPANYLINK###']	= 'http://' . $this->getFieldContent('companylink');
		$markerArray['###INFONAME###']		= $this->getFieldHeader('info');
		$markerArray['###INFODATA###']		= $this->getFieldContent('info');
		$markerArray['###IMAGENAME###']		= $this->getFieldHeader('image');

		$image = trim($this->getFieldContent('image'));
		if (!empty($image)) {
			$img = $this->conf['image.'];
			if (empty($img)) {
				$img['file'] = 'GIFBUILDER';
				$img['file.']['XY'] = '100, 150';
				$img['file.']['5'] = 'IMAGE';
			}
			$img['file.']['5.']['file'] = $uploadDirectory . '/' . $image;
			$markerArray['###IMAGELINK###'] = $this->cObj->IMAGE($img);
			$markerArray['###IMAGEFILE###'] = $uploadDirectory . '/' . $image;
		} else {
			$markerArray['###IMAGELINK###'] = '';
			$markerArray['###IMAGEFILE###'] = '';
		}
		$markerArray['###SESSIONSNAME###'] = $this->getFieldHeader('speakersessions');
		$this->internal['speakersessions'] = $sessionIds;
		$markerArray['###SESSIONS###'] = $this->getFieldContent('speakersessions');
		$markerArray['###BACKLINK###'] = $backLink;

		// For every session get information
		$content_item = '';
		if ($sessionIds) {
			$content_item = '';
			foreach (explode(',', $sessionIds) as $k){
				$sessionData = $this->pi_getRecord('tx_wseevents_sessions', $k);
				// Get overload language record
				if ($GLOBALS['TSFE']->sys_language_content) {
					$sessionData = $GLOBALS['TSFE']->sys_page->getRecordOverlay('tx_wseevents_sessions',
						$sessionData, $GLOBALS['TSFE']->sys_language_content,
						$GLOBALS['TSFE']->sys_language_contentOL, '');
				}
				$label = $sessionData['name'];
				if (!empty($this->conf['singleSession'])) {
					if (1 == $this->listView) {
						$overrulePiVars = array('showSessionUid' => $k, 'backUid' => $GLOBALS['TSFE']->id);
					} else {
						$overrulePiVars = array('showSessionUid' => $k);
					}
					$clearAnyway = 1;    // the current values of piVars will NOT be preserved
					$altPageId = $this->conf['singleSession'];      // ID of the target page, if not on the same page
					$this->setCache();
					$sessionName = $this->pi_linkTP_keepPIvars($label, $overrulePiVars,
						$this->useCache, $clearAnyway, $altPageId);
				} else {
					$sessionName = $label;
				}

				// Build content from template + array
				$markerArray1 = array();
				$markerArray1['###SESSIONNAME###'] = $sessionName;
				$markerArray1['###SESSIONTEASER###'] = $sessionData['teaser'];
				$markerArray1['###SESSIONDESCRIPTION###'] = $this->cObj->stdWrap($sessionData['description'],
					$this->conf['sessiondescription_stdWrap.']);
				$sessionData = $this->pi_getRecord('tx_wseevents_sessions', $k);
				$dataCat  = $this->pi_getRecord('tx_wseevents_categories', $sessionData['category']);
				$markerArray1['###SESSIONNUMBER###'] = $dataCat['shortkey'] . sprintf('%02d', $sessionData['number']);
				$markerArray1['###SESSIONCATEGORY###'] = $sessionData['category'];
				$markerArray1['###SESSIONCATEGORYKEY###'] = $dataCat['shortkey'];
				$markerArray1['###SESSIONCATEGORYCOLOR###'] = $dataCat['color'];
				// Get time slot info
				$timeSlotContent = '';
				foreach (explode(',', $sessionData['timeslots']) as $ts){
					$timeSlotData = $this->pi_getRecord('tx_wseevents_timeslots', $ts);
					$timeSlotName = $this->eventTimeSlots->formatSlotName($timeSlotData);
					if (!empty($timeSlotContent)) {
						$timeSlotContent .= $this->internal['slotdelimiter'] . $timeSlotName;
					} else {
						$timeSlotContent = $timeSlotName;
					}
				}
				$markerArray1['###SESSIONSLOTS###'] = $timeSlotContent;

				$content_item .= $this->cObj->substituteMarkerArrayCached($template['sessionrow'], $markerArray1);
			}
		}

//		$this->pi_getEditPanel();
		$subPartArray['###SESSIONROW###'] = $content_item;

		return $this->cObj->substituteMarkerArrayCached($template['total'], $markerArray, $subPartArray);
	}








	/**
	 * Get content of one field
	 *
	 * @param	string		$fN field name
	 * @return	string		field content
	 */
	function getFieldContent($fN)	{
		if (0 >= intval($this->internal['currentRow']['uid'])) {
			return $this->internal['currentRow']['uid'];
		}
		// get language overlay record for session table
		$sessionData = array();
		if ($this->internal['currentTable'] == 'tx_wseevents_sessions') {
			$sessionData = $this->internal['currentRow'];
			if ($GLOBALS['TSFE']->sys_language_content) {
				$sessionData = $GLOBALS['TSFE']->sys_page->getRecordOverlay('tx_wseevents_sessions',
					$sessionData, $GLOBALS['TSFE']->sys_language_content,
					$GLOBALS['TSFE']->sys_language_contentOL, '');
			}
		}
		switch($fN) {
			case 'uid':
				return $this->pi_list_linkSingle($this->internal['currentRow'][$fN],
					$this->internal['currentRow']['uid'], $this->useCache);
			break;

			case 'number':
				$dataCat = $this->pi_getRecord('tx_wseevents_categories', $this->internal['currentRow']['category']);
				$dataNum = $this->internal['currentRow'][$fN];
				return $dataCat['shortkey'] . sprintf ('%02d', $dataNum);
			break;

			case 'name':
				switch ($this->internal['currentTable']) {
					case 'tx_wseevents_sessions':
						return $sessionData['name'];
					break;
					case 'tx_wseevents_speakers':
						if (!empty($this->internal['currentRow']['firstname'])) {
							if ((isset($this->conf['lastnameFirst'])) && (1 == $this->conf['lastnameFirst'])) {
								return $this->internal['currentRow']['name'] . ', '
									. $this->internal['currentRow']['firstname'];
							} else {
								return $this->internal['currentRow']['firstname'] . ' '
									. $this->internal['currentRow']['name'];
							}
						} else {
							return $this->internal['currentRow']['name'];
						}
					break;
					default:
						return $this->internal['currentRow']['name'];
					break;
				}
			break;

			case 'teaser':
				switch ($this->internal['currentTable']) {
					case 'tx_wseevents_sessions':
						return $sessionData['teaser'];
					break;
					default:
						return $this->internal['currentRow'][$fN];
					break;
				}
			break;

			case 'description':
				switch ($this->internal['currentTable']) {
					case 'tx_wseevents_sessions':
						$data = $sessionData['description'];
						return $this->pi_RTEcssText($data);
					break;
					default:
						return $this->internal['currentRow'][$fN];
					break;
				}
			break;

			case 'room':
				$data = $this->pi_getRecord('tx_wseevents_rooms', $this->internal['currentRow'][$fN]);
				return $data['name'];
			break;

			case 'speaker':
				foreach (explode(',', $this->internal['currentRow'][$fN]) as $k){
					$data = $this->pi_getRecord('tx_wseevents_speakers', $k);
					// Get the name and firstname
					if (!empty($data['firstname'])) {
						if (((isset($this->conf['lastnameFirst']))) && (1 == $this->conf['lastnameFirst'])) {
							$label =  $data['name'] . ', ' . $data['firstname'];
						} else {
							$label =  $data['firstname'] . ' ' . $data['name'];
						}
					} else {
						$label =  $data['name'];
					}

					if (!empty($this->conf['singleSpeaker'])) {
						if (1 == $this->listView) {
							$overrulePiVars = array('showSpeakerUid' => $data['uid'], 'backUid' => $GLOBALS['TSFE']->id);
						} else {
							$overrulePiVars = array('showSpeakerUid' => $data['uid']);
						}

						$clearAnyway = 1;    // the current values of piVars will NOT be preserved
						$altPageId = $this->conf['singleSpeaker'];      // ID of the target page, if not on the same page
						$this->setCache();
						$speakerName = $this->pi_linkTP_keepPIvars($label, $overrulePiVars,
							$this->useCache, $clearAnyway, $altPageId);
						if (empty($label)) {
							$speakerName = '';
						}
					} else {
						$speakerName = $label;
					}
					if (isset($content)) {
						$content .= $this->internal['speakerdelimiter'] . $speakerName;
					} else {
						$content = $speakerName;
					}
				}
				if (empty($content)) {
					$content = $this->pi_getLL('tx_wseevents_sessions.nospeakers', '[no speaker assigned]');
				}
				return $content;
			break;

			case 'speakersessions':
				foreach (explode(',', $this->internal['speakersessions']) as $k){
					$data = $this->pi_getRecord('tx_wseevents_sessions', $k);
					// Get overload language record
					if ($GLOBALS['TSFE']->sys_language_content) {
						$data = $GLOBALS['TSFE']->sys_page->getRecordOverlay('tx_wseevents_sessions',
							$data, $GLOBALS['TSFE']->sys_language_content,
							$GLOBALS['TSFE']->sys_language_contentOL, '');
					}

					$label = $data['name'];
					if (!empty($this->conf['singleSession'])) {
						if (1 == $this->listView) {
							$overrulePiVars = array('showSessionUid' => $data['uid'], 'backUid' => $GLOBALS['TSFE']->id);
						} else {
							$overrulePiVars = array('showSessionUid' => $data['uid']);
						}
						$clearAnyway = 1;    // the current values of piVars will NOT be preserved
						$altPageId = $this->conf['singleSession'];      // ID of the target page, if not on the same page
						$this->setCache();
						$sessionName = $this->pi_linkTP_keepPIvars($label, $overrulePiVars,
							$this->useCache, $clearAnyway, $altPageId);
					} else {
						$sessionName = $label;
					}
					if (!empty($content)) {
						$content .= $this->internal['sessiondelimiter'] . $sessionName;
					} else {
						$content = $sessionName;
					}
					if (!empty($this->conf['singleSessionSlot'])) {
						// ToDo: Here the timeslots must be read and added to the content
					}
				}
				if (empty($content)) {
					$content = '';
				}
				return $content;
			break;

			case 'timeslots':
				if (0 == $this->internal['hideTimeslots']) {
					foreach (explode(',', $this->internal['currentRow'][$fN]) as $k){
						$data = $this->pi_getRecord('tx_wseevents_timeslots', $k);
						$timeSlotName = $this->eventTimeSlots->formatSlotName($data);
						if (1 == $this->conf['showCalendarLink']) {
							// Create link for iCal download
							$overrulePiVars = array('sessionUid' => $this->internal['currentRow']['uid'], 'slotUid' => $k, 'download' => 'iCal');
							if (!empty($this->conf['calendarLinkLabel'])) {
								$label = $this->conf['calendarLinkLabel'];
							} else {
								$label = 'iCal';  // the link text
							}
							$iCalLinkName = $this->pi_linkTP_keepPIvars($label, $overrulePiVars, $this->useCache);
							$timeSlotName .= ' ' . $iCalLinkName;
						}
						if (isset($content)) {
							$content .= $this->internal['slotdelimiter'] . $timeSlotName;
						} else {
							$content = $timeSlotName;
						}
					}
				}
				if (empty($content)) {
					$content = $this->pi_getLL('tx_wseevents_sessions.notimeslots', '[not yet sheduled]');
				}
				return $content;
			break;

			case 'info':
				switch ($this->internal['currentTable']) {
					case 'tx_wseevents_speakers':
						$data = $this->internal['currentRow'];
						// Get overload language record
						if ($GLOBALS['TSFE']->sys_language_content) {
							$data = $GLOBALS['TSFE']->sys_page->getRecordOverlay('tx_wseevents_speakers',
								$data, $GLOBALS['TSFE']->sys_language_content,
								$GLOBALS['TSFE']->sys_language_contentOL, '');
						}
						$field = $data['info'];
						return $this->pi_RTEcssText($field);
					break;
					default:
						return $this->internal['currentRow'][$fN];
					break;
				}
			break;

			case 'country':
				$data = $this->pi_getRecord('static_countries', $this->internal['currentRow'][$fN]);
				$iso = $data['cn_iso_3'];
				return $this->staticInfo->getStaticInfoName('COUNTRIES', $iso);
					// . ':' . $iso . ':' . $this->staticInfo->getCurrentLanguage();
			break;

			case 'documents':
				// Check if any presentation handouts are available
				if (empty($this->internal['currentRow'][$fN])) {
					// if not then check for the date and get back a message if event is in the past
					$eventDate = date('Ymd', $this->eventRecord['begin']);
					$thisDate = date('Ymd');
					if ($thisDate>=$eventDate) {
						$docContent = $this->pi_getLL('tx_wseevents_sessions.nohandout');
					}
				} else {
					foreach (explode(',', $this->internal['currentRow'][$fN]) as $k){
						// ToDo: Ticket #15, #17, Implement presentation material downloads via TYPO3 file link
						// http://trac.netlabs.org/wse_events/ticket/15
						// http://trac.netlabs.org/wse_events/ticket/17
						$documentsName = '<a href="uploads/tx_wseevents/' . $k . '" '
							. $this->documentsTarget . '>' . $k . '</a>';
						if (isset($docContent)) {
							$docContent .= $this->internal['documentsdelimiter'] . $documentsName;
						} else {
							$docContent = $documentsName;
						}
					}
				}
				if (empty($docContent)) {
					$docContent = '';
				}
				return $docContent;
			break;
		}
		return $this->internal['currentRow'][$fN];
	}




	/**
	 * Get list of session UIDs of a speaker for an event
	 *
	 * @param	integer		$speakerId speaker id
	 * @param	integer		$eventPid id of system folder with event data
	 * @return	string		comma separated list of sessions for the speaker
	 */
	function getSpeakerSessionList($speakerId, $eventPid) {
		$sessions = '';
		$where = 'sys_language_uid=0' . $this->cObj->enableFields('tx_wseevents_sessions') . ' AND pid=' . $eventPid;
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('speaker, uid', 'tx_wseevents_sessions', $where);
		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			foreach (explode(',', $row['speaker']) as $k){
				if ($k==$speakerId) {
					if (empty($sessions)) {
						$sessions = $row['uid'];
					} else {
						$sessions .= ',' . $row['uid'];
					}
				}
			}
		}
		$GLOBALS['TYPO3_DB']->sql_free_result($res);
		return $sessions;
	}




	/**
	 * Get label of one field from language file
	 *
	 * @param	string		$fN field name
	 * @return	string		header for the field
	 */
	function getFieldHeader($fN)	{
		switch($fN) {
			default:
				return $this->pi_getLL($this->internal['currentTable'] . '.listFieldHeader_' . $fN, '[' . $fN . ']');
			break;
		}
	}


	/**
	 * Get info about rooms of an location
	 *
	 * @param	integer		$loc_id id of location
	 * @return	array		array with record data of all rooms of a location
	 */
	function getRoomInfo($loc_id) {
		$where = 'sys_language_uid=0 AND location=' . $loc_id . $this->cObj->enableFields('tx_wseevents_rooms');
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid, name, comment, seats, number',
			'tx_wseevents_rooms', $where, 'number');
		$id = 1;
		$rows = array();
		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$rows[$id] = $row;
			$id++;
		}
		$GLOBALS['TYPO3_DB']->sql_free_result($res);
		return $rows;
	}


	/**
	 * Get info about categories of an event
	 *
	 * @param	integer		$event_id id of event
	 * @return	array		array with record data of all categories of an event
	 */
	function getCategoryInfo($event_id) {
		$where = 'event=' . $event_id . ' AND sys_language_uid=0 ' . $this->cObj->enableFields('tx_wseevents_sessions');
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('category', 'tx_wseevents_sessions', $where, '');
		$categoryIds = array();
		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$categoryIds[] = $row['category'];
		}
		return $this->eventTimeSlots->getSelectedCategories(array_unique($categoryIds), 'shortkey');
	}


	/**
	 * Check if a room is occupied on a day
	 *
	 * @param	integer		$event id of event
	 * @param	integer		$day number of the event day
	 * @param	integer		$room number of the event location room
	 * @param	integer		$showDbgSql flag to show debug output of SQL query
	 * @return	integer		count of slots
	 */
	function checkRoom($event, $day, $room, $showDbgSql) {
		$where = 'event=' . $event . ' AND eventday=' . $day . ' AND room=' . $room
			. $this->cObj->enableFields('tx_wseevents_timeslots');
		if (1 == $showDbgSql) { echo 'checkRoom where:' . $where . '<br>'; };
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid', 'tx_wseevents_timeslots', $where);
		if ($res) {
			$count = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
			$GLOBALS['TYPO3_DB']->sql_free_result($res);
			if (1 == $showDbgSql) { echo 'checkRoom return:' . $count . '<br>'; };
			return $count;
		} else {
			if (1 == $showDbgSql) { echo 'checkRoom return:0<br>'; };
			return 0;
		}
	}


	/**
	 * Get speaker names for a list of speaker id's
	 * Check TS setting 'lastnameFirst' for "lastname, firstname" order
	 * Concat speaker with TS setting 'speakerdelimiter'
	 *
	 * @param	string		$speakerList list of speaker id's, comma separated
	 * @return	string		string with list of speakers
	 */
	function getSpeakerNames($speakerList) {
		foreach (explode(',', $speakerList) as $k){
			$data = $this->pi_getRecord('tx_wseevents_speakers', $k);
			// Get the name and firstname, if firstname is available
			if (!empty($data['firstname'])) {
				// Check TS setting for lastname, firstname
				if (((isset($this->conf['lastnameFirst']))) && (1 == $this->conf['lastnameFirst'])) {
					$speakerName =  $data['name'] . ', ' . $data['firstname'];
				} else {
					$speakerName =  $data['firstname'] . ' ' . $data['name'];
				}
			} else {
				$speakerName =  $data['name'];
			}
			if (isset($speaker_content)) {
				// Second and furter name(s)
				$speaker_content .= $this->internal['speakerdelimiter'] . $speakerName;
			} else {
				// First name
				$speaker_content = $speakerName;
			}
		}
		// Check if any speaker was in the list
		if (empty($speaker_content)) {
			$speaker_content = $this->pi_getLL('tx_wseevents_sessions.nospeakers', '[no speaker assigned]');
		}
		return $speaker_content;
	}

	/**
	 * Set the pi_USER_INT_obj variable depending on cache use
	 *
	 * @return	void
	 */
	function setCache() {
		if (1 == $this->useCache) {
			$this->pi_USER_INT_obj = 0;
		} else {
			$this->pi_USER_INT_obj = 1;
		}
	}

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/wse_events/pi1/class.tx_wseevents_pi1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/wse_events/pi1/class.tx_wseevents_pi1.php']);
}

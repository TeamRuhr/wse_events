<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

t3lib_extMgm::addUserTSConfig('
	options.saveDocNew.tx_wseevents_speakers=1
');
t3lib_extMgm::addUserTSConfig('
	options.saveDocNew.tx_wseevents_speakerrestrictions=1
');

  #// Extending TypoScript from static template uid=43 to set up userdefined tag:
t3lib_extMgm::addTypoScript($_EXTKEY,'editorcfg','
	tt_content.CSS_editor.ch.tx_wseevents_pi1 = < plugin.tx_wseevents_pi1.CSS_editor
',43);


t3lib_extMgm::addPItoST43($_EXTKEY,'pi1/class.tx_wseevents_pi1.php','_pi1','list_type',1);


t3lib_extMgm::addTypoScript($_EXTKEY,'setup','
	tt_content.shortcut.20.0.conf.tx_wseevents_events = < plugin.'.t3lib_extMgm::getCN($_EXTKEY).'_pi1
	tt_content.shortcut.20.0.conf.tx_wseevents_events.CMD = singleView
',43);

// Include and register our class for the hook of process_datamap
$GLOBALS ['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = 'EXT:wse_events/class.tx_wseevents_tcemainprocdm.php:tx_wseevents_tcemainprocdm';

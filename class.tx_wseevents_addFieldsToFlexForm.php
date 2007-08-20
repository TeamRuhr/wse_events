<?php
class tx_wseevents_addFieldsToFlexForm {
 function addFields ($config) {
   $optionList = array();
   // add option to display the sessions
   $optionList[0] = array(0 => 'session list', 1 => 'sessionlist');
   // add option to display the speakers
   $optionList[1] = array(0 => 'session detail', 1 => 'sessiondetail');
   // add option to display the time slots
   $optionList[2] = array(0 => 'speaker list', 1 => 'speakerlist');
   $optionList[3] = array(0 => 'time slots', 1 => 'timeslots');
   $config['items'] = array_merge($config['items'],$optionList);
   return $config;
 }
}
?>
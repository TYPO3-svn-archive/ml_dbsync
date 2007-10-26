<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007 Markus Friedrich <markus.friedrich@media-lights.de>
*  All rights reserved
*
*  This script is part of the Typo3 project. The Typo3 project is
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
 * Class "ext_update" used to schedule events to the gabriel ext or 
 * removes them from the execution list
 *
 * @author	Markus Friedrich <markus.friedrich@media-lights.de>
 * @package TYPO3
 * @subpackage	ml_dbsync
 */
class ext_update  {

 	/**
	 * This function is called upon extension update request and changes the execution status.
	 * It adds / removes "ml_dbsync" to/from gabriels execution list
	 *
	 * @return	string	HTML message
	 * @access	public
	 */	
	function main()	{
		//check if gabriel is loaded
		t3lib_extMgm::isLoaded('gabriel', 'true');
		
	
		require_once(t3lib_extMgm::extPath('ml_dbsync', 'class.tx_mldbsync_gabriel.php'));
		$db =& $GLOBALS['TYPO3_DB'];
		$msg = '';
	
		//make instances	
		$sync = t3lib_div::makeInstance('tx_mldbsync_gabriel');
		$gabriel =& t3lib_div::getUserObj('EXT:gabriel/class.tx_gabriel.php:&tx_gabriel');
		
		//check if dbsync is already registered to gabriel
		$RS = $db->exec_SELECTquery('uid', 'tx_gabriel', 'crid="tx_mldbsync"');
		
		if ($row = $db->sql_fetch_assoc($RS)) {
			$sync->eventUid = $row['uid'];
			$gabriel->removeEvent($sync);
			$msg = '<b>Dbsync has been removed from gabriels execution list</b>';
			
		} else {
			//register recurring execution (once a week at 01:00 o'clock')
			$sync->registerRecurringExecution(strtotime(date('Y-m-d 01:00:00')), 60*60*24*7, strtotime('+10 years'));

			//add event			
			$gabriel->addEvent($sync, 'tx_mldbsync');
	
			$msg = '<b>Dbsync has been added to gabriel</b>';
		}
	
		return $msg;
	} //end of main()

	/**
	 * Installs the "UPDATE!" menu item
	 * 
	 * @return	boolean		true
	 */	
	function access() {
		return true;
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ml_dbsync/class.ext_update.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ml_dbsync/class.ext_update.php']);
}
?>

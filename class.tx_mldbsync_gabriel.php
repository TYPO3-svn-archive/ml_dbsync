<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007 Markus Friedrich (markus.friedrich@media-lights.de)
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
 * Class "tx_mldbsync" provides database syncronisations via gabriel
 *
 * @author	Markus Friedrich <markus.friedrich@media-lights.de>
 * @package TYPO3
 * @subpackage	tx_dbsync
 */


require_once(t3lib_extMgm::extPath('gabriel', 'class.tx_gabriel_event.php'));
require_once(t3lib_extMgm::extPath('ml_dbsync', 'class.tx_mldbsync.php'));

class tx_mldbsync_gabriel extends tx_gabriel_event {

	/**
	 * PHP4 wrapper function for the class constructor
	 * 
	 * @return 	void
	 */
	function tx_mldbsync_gabriel() {
		$this->__construct();
	} // end of 'function tx_mldbsync_gabriel() {..}'
	
	
	/**
	 * Function executed from gabriel.
	 * Starts database syncronisation
	 *
	 * @return	void
	 */
	function execute() {
		global $TYPO3_DB;

		//create ml_dbsync instance	
		$dbsync = t3lib_div::makeInstance('tx_mldbsync');
		
		$checkLogfile = $dbsync->checkLockfile();
		if ($checkLogfile < 2) {
			if ($checkLogfile == 1) {
				echo 'Old Lockfile was found, assuming a crashed prior import and starting this import!';
			}
			
			//get xml files from database
			$xmlFiles = array();
			$xml_query = $TYPO3_DB->exec_SELECTquery('*', 'tx_mldbsync_xmlfiles', 'gabriel_enabled=\'1\'');
			while ($xml = $TYPO3_DB->sql_fetch_assoc($xml_query)) { 
				$xmlFiles[] = array(
					'file' => $xml['file'],
					'pid' => $xml['pid'],
					'hidden' => $xml['hidden'],
					'fileid' => $xml['fileid'],
					'active' => 1,
				);
			}
		
			//syncronise
			$dbsync->startDatabaseImport($xmlFiles);
		} else {
			echo 'Lockfile is present, aborting import process!';
		}
	} // end of 'function execute() {..}'
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ml_dbsync/class.tx_mldbsync_gabriel.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ml_dbsync/class.tx_mldbsync_gabriel.php']);
}

?>

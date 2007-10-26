<?php
/***************************************************************
*  Copyright notice
*  
*  (c) 2006 Markus Friedrich (markus.friedrich@media-lights.de)
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
 * User extension of class SC_index for the 'ml_dbsync'-extension 
 *
 * @author	Markus Friedrich <markus.friedrich@media-lights.de>
 * @package TYPO3
 * @subpackage	tx_dbsync
 */
class ux_SC_index extends SC_index {
	
	/**
	* Creates the login form
	* This is drawn when NO login exists.
	*
	* @return      string          HTML output
	*/
	function makeLoginForm()        {
		$RS = $GLOBALS['TYPO3_DB']->exec_SELECTquery('1', 'be_users', 'disable="2"');
		if ($GLOBALS['TYPO3_DB']->sql_num_rows($RS) > 0) {
			$form = array();
			
			$form[] = '';
			$form[] = '<!--';
			$form[] = 'Login impossible form:';
			$form[] = '-->';
			$form[] = '<table cellspacing="0" cellpadding="0" border="0" id="logintable" style="margin: 0px auto; width: 40%;">';
			$form[] = '<tr>';
			$form[] = "\t" . '<td><img src="../'.t3lib_extMgm::siteRelPath('ml_dbsync').'error.gif" alt="Login denied" /></td>';
			$form[] = "\t" . '<td><h2>Login denied</h2></td>';
			$form[] = '</tr>';
			$form[] = '<tr>';
			$form[] = "\t" . '<td></td>';
			$form[] = "\t" . '<td><p class="c-info">Login is not possible at the moment due to a running syncronisation process. After the syncronisation has finished login will be possible as usual.</p></td>';
			$form[] = '</tr>';
			$form[] = '</table>';

			$returnValue = implode("\n\t\t\t\t\t\t\t", $form);
		} else {
			$returnValue = parent::makeLoginForm();
		}
			
		return $returnValue;
	}

}

if (defined("TYPO3_MODE") && $TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/ml_dbsync/class.ux_SC_index.php"])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/ml_dbsync/class.ux_SC_index.php"]);
}

?>

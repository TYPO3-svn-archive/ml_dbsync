<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2006-2007 Markus Friedrich (markus.friedrich@media-lights.de)
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

	// DEFAULT initialization of a module [BEGIN]
unset($MCONF);
require ('conf.php');
require_once ($BACK_PATH.'init.php');
require_once ($BACK_PATH.'template.php');
$LANG->includeLLFile('EXT:ml_dbsync/mod1/locallang.xml');
$BE_USER->modAccess($MCONF,1);	
	// DEFAULT initialization of a module [END]

require_once(t3lib_extMgm::extPath('ml_dbsync', 'class.tx_mldbsync.php'));


/**
 * Module 'DB Sync' for the 'ml_dbsync' extension.
 *
 * @author	Markus Friedrich <markus.friedrich@media-lights.de>
 * @package TYPO3
 * @subpackage	tx_dbsync
 */
class tx_mldbsync_module1 extends t3lib_SCbase {
	var $dbsync = NULL;	//dbsync instance
	var $inputFieldsXML = 3;
	

	/**
	 * initializes the module
	 */
	function init()	{
		global $BE_USER,$LANG,$BACK_PATH,$TCA_DESCR,$TCA,$CLIENT,$TYPO3_CONF_VARS;

		parent::init();
		
		//create instance of dbsync
		$this->dbsync = t3lib_div::makeInstance('tx_mldbsync');
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
				'1' => $LANG->getLL('function1'),
				'2' => $LANG->getLL('function2'),
			)
		);
		parent::menuConfig();
	}

	/**
	 * Main function of the module. Write the content to $this->content
	 * 
	 * @return	void
	 */
	function main()	{
		global $BE_USER,$LANG,$BACK_PATH,$TCA_DESCR,$TCA,$CLIENT,$TYPO3_CONF_VARS;
		// Access check!
		// The page will show only if there is a valid page and if this page may be viewed by the user
		$this->pageinfo = t3lib_BEfunc::readPageAccess($this->id,$this->perms_clause);
		$access = is_array($this->pageinfo) ? 1 : 0;

		if ($BE_USER->user['admin'])	{

				// Draw the header.
			$this->doc = t3lib_div::makeInstance('bigDoc');
			$this->doc->backPath = $BACK_PATH;

				// JavaScript
			$this->doc->JScode = '
				<script language="javascript" type="text/javascript">
					var script_ended;
					script_ended = 0;
					function jumpToUrl(URL)	{
						document.location = URL;
					}
				</script>
			';
			$this->doc->postCode='
				<script language="javascript" type="text/javascript">
					script_ended = 1;
					if (top.fsMod) top.fsMod.recentIds["web"] = '.intval($this->id).';
				</script>
			';

			$headerSection = $this->doc->getHeader('pages',$this->pageinfo,$this->pageinfo['_thePath']).'<br />'.$LANG->sL('LLL:EXT:lang/locallang_core.php:labels.path').': '.t3lib_div::fixed_lgd_pre($this->pageinfo['_thePath'],50);

			$this->content.=$this->doc->startPage($LANG->getLL('title'));
			$this->content.=$this->doc->header($LANG->getLL('title'));
			$this->content.=$this->doc->spacer(5);
			$this->content.=$this->doc->section('',$this->doc->funcMenu($headerSection,t3lib_BEfunc::getFuncMenu($this->id,'SET[function]',$this->MOD_SETTINGS['function'],$this->MOD_MENU['function'])));
			$this->content.=$this->doc->divider(5);


			// Render content:
			$this->moduleContent();


			// ShortCut
			if ($BE_USER->mayMakeShortcut())	{
				$this->content.=$this->doc->spacer(20).$this->doc->section('',$this->doc->makeShortcutIcon('id',implode(',',array_keys($this->MOD_MENU)),$this->MCONF['name']));
			}

			$this->content.=$this->doc->spacer(10);
		} else {
				// If no access or if ID == zero

			$this->doc = t3lib_div::makeInstance('mediumDoc');
			$this->doc->backPath = $BACK_PATH;

			$this->content.=$this->doc->startPage($LANG->getLL('title'));
			$this->content.=$this->doc->header($LANG->getLL('title'));
			$this->content.=$this->doc->spacer(5);
			$this->content.=$this->doc->spacer(10);
		}
	}

	/**
	 * Prints out the module HTML
	 * 
	 * @return	void
	 */
	function printContent()	{

		$this->content.=$this->doc->endPage();
		echo $this->content;
	}

	/**
	 * Generates a link to the browse_links-wizard
	 *
	 * @param 	string		active sheet
	 * @param 	string		form name
	 * @param	string		name of the form field
	 * @return	string		link to the wizard
	 */
	function createLinkToBrowseLinks($act, $form, $field){

		$browseLinksFile = $this->doc->backPath.'browse_links.php';	
		$params = array(
			'act' => $act,
			'mode' => 'wizard',
			'field' => $field,
			'table' => 'pages',
			'P[returnUrl]' => t3lib_div::linkThisScript(),
			'P[field]' => 'header_link',
			'P[formName]' => $form,
			'P[itemName]' => $field,
			'P[fieldChangeFunc][focus]' => 'focus()',
		);

		$linkToScript = t3lib_div::linkThisUrl($browseLinksFile, $params);
	
		$link =
			'<a href="#" onclick="'.'this.blur(); vHWin=window.open(\''.$linkToScript.'\',\'\',' . 
			'\'height=300,width=500,status=0,menubar=0,scrollbars=1\');' . 
			'vHWin.focus();return false;">' . 
			'<img src="../link_popup.gif" />' .
			'</a>' . chr(10);

		return $link;
	}


	/**
	 * Generates the input form
	 * 
	 * @return	string		input form
	 */
	function drawInputForm(){
		global $LANG, $TYPO3_DB;
		

		//check if there are saved fields, else display empty fields
		if (is_null($xmlFiles = t3lib_div::_POST('xmlFiles'))) {
			$xmlFiles = array();
			$files = $TYPO3_DB->exec_SELECTquery('*', 'tx_mldbsync_xmlfiles', '');
			while ($file = $TYPO3_DB->sql_fetch_assoc($files)) {
				$xmlFiles[] = $file;
			}
			
			if (($inputFieldsXML = $TYPO3_DB->sql_num_rows($files) + 2) == 2) {
				$inputFieldsXML = $this->inputFieldsXML;
			}
		}
		else {
			if (is_null($inputFieldsXML = t3lib_div::_POST('inputFieldsXML'))) {
				$inputFieldsXML = $this->inputFieldsXML;
			}

			if (!is_null($addFields = t3lib_div::_POST('addFields'))) {
				$inputFieldsXML += 2;
			}
		}

		//remove field if demanded
		if (!is_null($removeField = t3lib_div::_POST('removeField'))) {
			$TYPO3_DB->exec_DELETEquery('tx_mldbsync_xmlfiles', 'fileid='.$TYPO3_DB->fullQuoteStr($xmlFiles[key($removeField)]['fileid'], 'tx_mldbsync_xmlfiles')); 
			unset($xmlFiles[key($removeField)]);
		}
		
		
		$content = sprintf(
			'<form name="editform" action="%2$s" method="post">' . '%1$s' .
			'<input type="hidden" name="action" value="startImport">' . '%1$s' .
			'<input type="hidden" name="inputFieldsXML" value="%3$s">' . '%1$s' .

			'<table>' . '%1$s' .
			'<tr>' . '%1$s' .
			'<td>&nbsp;</td>' . '%1$s' .
			'<td colspan="2">%4$s</td>' . '%1$s' .
			'<td colspan="2">%5$s</td>' . '%1$s' .
			'<td>%6$s</td>' . '%1$s' .
			'<td>&nbsp;</td>' . '%1$s' .
			'<td>&nbsp;</td>' . '%1$s' .
			'<td>&nbsp;</td>' . '%1$s' .
			'</tr>' . '%1$s%1$s',
			
			chr(10),
			t3lib_div::linkThisScript(),
			$inputFieldsXML,
			$LANG->getLL('xmlFile'),
			$LANG->getLL('pid'),
			$LANG->getLL('importMode')		
		);

		for ($i=0; $i<$inputFieldsXML; $i++) {
			if (isset($xmlFiles[$i])) {
				$file = $xmlFiles[$i]['file'];
				$pid = $xmlFiles[$i]['pid'];
				
				if ($xmlFiles[$i]['hidden'] == 0) {
					$selected = ' selected="selected"';
				} else $selected = '';

				if ($xmlFiles[$i]['active'] == 1) {
					$checked = ' checked="checked"';
				} else $checked = '';

				if (isset($xmlFiles[$i]['fileid'])) {
					$hint = $LANG->getLL('hint_update');
					$delBtn = '<input type="submit" name="removeField['.$i.']" value="'.$LANG->getLL('removeFile').'"/>';
					$importBtn = '<input type="submit" name="importFile['.$i.']" value="'.$LANG->getLL('importFile').'"/>';
				} else {
					$hint = $LANG->getLL('hint_import'); 
					$delBtn = '';
					$importBtn = '';
				}

			} else {
				$file = '';
				$pid = '';
				$hidden = 1;
				$checked = '';
				$hint = $LANG->getLL('hint_import');
				$delBtn = '';
				$importBtn = '';
			}
			
			$content .= sprintf(
				'<tr>' . '%1$s' .
				'<td><input type="checkbox" name="xmlFiles[%3$s][active]" value="1" %4$s/></td>' . '%1$s' .
				'<td><input type="text" name="xmlFiles[%3$s][file]" value="%5$s" style="width: 300px;" /></td>'.'%1$s' .
				'<td>%6$s</td>'.'%1$s' .
				'<td><input type="text" name="xmlFiles[%3$s][pid]" value="%7$s" style="width: 50px;"/></td>'.'%1$s' .
				'<td>%8$s</td>'.'%1$s' .
				'<td><select name="xmlFiles[%3$s][hidden]" >' . '%1$s' .
				'%2$s' . '<option value="1">%9$s</option>'.'%1$s' .
				'%2$s' . '<option value="0" %10$s>%11$s</option>'.'%1$s' .
				'</select>' . '%1$s' .
				'<td>%12$s</td>' . '%1$s' .
				'<td>%13$s</td>' . '%1$s' .
				'<td>%14$s</td>' . '%1$s' .
				'</tr>' . '%1$s%1$s',
				
				chr(10),
				chr(9),
				$i,
				$checked,
				$file,
				$this->createLinkToBrowseLinks('file','editform','xmlFiles['.$i.'][file]'),
				$pid,
				$this->createLinkToBrowseLinks('page', 'editform', 'xmlFiles['.$i.'][pid]'),
				$LANG->getLL('importPagesAsHidden'),
				$selected,
				$LANG->getLL('importPagesAsVisible'),
				$hint,
				$delBtn,
				$importBtn				
			);

			if (isset($xmlFiles[$i]['fileid'])) {
				$content .= '<input type="hidden" name="xmlFiles['.$i.'][fileid]" value="'.$xmlFiles[$i]['fileid'].'" />' . "\n";
			}

		} // end of "for ($i=0; $i<$inputFieldsXML; $i++) {}"

		$content .= sprintf(
			'<tr>' . '%1$s' .
			'<td colspan="8">' . '%1$s' .
			'<input type="submit" name="addFields" value="%2$s" />' . '%1$s' . 
			'<input type="submit" value="%3$s" />' . '%1$s' . 
			'</td>' . '%1$s' .
			'</tr>' . '%1$s' .
			'</table>' . '%1$s' . 
			'</form>' . '%1$s',
			
			chr(10),
			$LANG->getLL('addFields'),
			$LANG->getLL('submitBtn')
		);
		
		
		//display download log option if log is present
		if (@file_exists(PATH_site.'uploads/tx_mldbsync/tx_mldbsync.log')) {
			$content .= sprintf('<br /><br /><a href="%1$s" title="%2$s">%2$s</a>',
				t3lib_div::linkThisScript(array('action' => 'download')),
				$LANG->getLL('download_logfile')
			);
		}

		
		return $content;
	}


	/**
	 * Generates the input form
	 * 
	 * @return	string		import messages
	 */
	function displayImportMessages() {
		global $LANG;
		$content = '';

		if (!empty($this->dbsync->summary)) {

			foreach ($this->dbsync->summary AS $file => $data) {
		 		if ($data['importMode'] == 0) {
		 			$importMode = $LANG->getLL('importPagesAsVisible');
		 		} else { $importMode = $LANG->getLL('importPagesAsHidden'); }
	 		
				$content.= sprintf(
					'<strong>%2$s</strong><br />' . '%1$s' .
					'<table>' . '%1$s' .
					'<tr><td>%3$s</td><td>%4$s</td></tr>' . '%1$s' .
					'<tr><td>%5$s</td><td>%6$s</td></tr>' . '%1$s' .
					'<tr><td>%7$s</td><td>%8$s</td></tr>' . '%1$s' .
					'<tr><td>%9$s</td><td>%10$s</td></tr>' . '%1$s' .
					'<tr><td>%11$s</td><td>%12$s</td></tr>' . '%1$s' .
					'<tr><td>%13$s</td><td>%14$s</td></tr>' . '%1$s' .
					'<tr><td>%15$s</td><td>%16$s</td></tr>' . '%1$s' .
					'<tr><td>%17$s</td><td>%18$s</td></tr>' . '%1$s' .
					'<tr><td>%19$s</td><td>%20$s</td></tr>' . '%1$s' .
					'<tr><td>%21$s</td><td>%22$s</td></tr>' . '%1$s' .
					'<tr><td>%23$s</td><td>%24$s</td></tr>' . '%1$s' .
					'</table><br />' . '%1$s%1$s',
				
					chr(10),
					$file,
				
					$LANG->getLL('pid'),
		 			$data['pid'],
		 			
		 			$LANG->getLL('database'),
		 			$data['database'],
		 			
		 			$LANG->getLL('importMode'),
		 			$importMode,
		 			
		 			$LANG->getLL('createdPages'),
		 			$data['createdPages'],
		 			
		 			$LANG->getLL('updatedPages'),
		 			$data['updatedPages'],
		 			
		 			$LANG->getLL('createdContents'),
		 			$data['createdContents'],
		 			
		 			$LANG->getLL('updatedContents'),
		 			$data['updatedContents'],
		 			
		 			$LANG->getLL('createdPages_error'),
		 			$data['createdPages_error'],
		 			
		 			$LANG->getLL('updatedPages_error'),
		 			$data['updatedPages_error'],
		 			
		 			$LANG->getLL('createdContents_error'),
		 			$data['createdContents_error'],
		 			
		 			$LANG->getLL('updatedContents_error'),
		 			$data['updatedContents_error']
				);
			}
		} else {
			$content .= $LANG->getLL('nothingToImport') . chr(10);
		}


		//add back btn
		$content .= sprintf(
			'<form action="%2$s" method="get">' . '%1$s' .
			'<input type="submit" value="%3$s" />' . '%1$s' .
			'</form>' . '%1$s',
			
			chr(10),
			t3lib_div::linkThisScript(),
			$LANG->getLL('btn_back')
		);

		return $content;
	}

	/**
	 * Displays XML Form, used for configuration
	 * 
	 * @return	string		xml form
	 */
	function displayXmlForm() {
		global $LANG, $TYPO3_DB;
		$content = '';
		
		$content.= '<strong>' . $LANG->getLL('msg_configureXml') . '</strong>' .
			'<table id="xmlfiles">';
			
		$xml_query = $TYPO3_DB->exec_SELECTquery('*', 'tx_mldbsync_xmlfiles', '');
		while ($xml = $TYPO3_DB->sql_fetch_assoc($xml_query)) {
			if ($xml['gabriel_enabled']) {
				$action = 'deactivate';
				$btn_label = $LANG->getLL('btn_deactivate');	
			} else {
				$action = 'activate';
				$btn_label = $LANG->getLL('btn_activate');	
			}
		
			$content.= sprintf(
				'<tr>' .
				'<td>%s</td>' .
				'<td>%s</td>' .
				'<td>' .
				'<form action="" method="post">' .
				'<input type="hidden" name="action" value="%s"/>' .
				'<input type="hidden" name="uid" value="%s"/>' .
				'<input type=submit value="%s" />' .
				'</form>' .
				'</td>' .
				'<tr>',
				
				$xml['file'],
				$xml['pid'],
				$action,
				$xml['fileid'],
				$btn_label
			);
				
				
		}
			
		$content .=	'</table>';
		
		return $content;
	}


	/**
	 * Sends dbsync logfile to the browser
	 * 
	 * @return	void
	 */
	function sendLogfile() {
		if (@file_exists(PATH_site.'uploads/tx_mldbsync/tx_mldbsync.log')) {
			$content = t3lib_div::getURL(PATH_site.'uploads/tx_mldbsync/tx_mldbsync.log');
			
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename=tx_mldbsync.log');
			header('Content-Length: '.strlen($content));
			header('Pragma: no-cache');
			header('Expires: 0');
			echo $content;
		} else { 
			echo 'ERROR: Logfile not found!';
		}
	}

	/**
	 * Generates the module content
	 * 
	 * @return	void
	 */
	function moduleContent()	{
		global $LANG, $TYPO3_DB;
		
		switch((string)$this->MOD_SETTINGS['function'])	{
			case 1:
				if ($this->dbsync->checkLockfile() < 2) {
			
					$action = t3lib_div::_GP('action');
					$addFields = t3lib_div::_POST('addFields');
					$removeField = t3lib_div::_POST('removeField');
				
					//check if only fields should be added
					if (!is_null($addFields) || !is_null($removeField)) {
						$action = '';
					}
				
					/* perform required actions */
					switch ($action) {
						case 'download':
							$this->sendLogfile();	
							exit;
						case 'startImport':
							//get xmlFiles
							$xmlFiles = t3lib_div::_POST('xmlFiles');
					
							//check if a single file was submitted
							if (!is_null($file = t3lib_div::_POST('importFile'))) {
				
								//insert this element as the only one
								$xmlFiles = array(array(
									'file' => $xmlFiles[key($file)]['file'],
									'pid' => $xmlFiles[key($file)]['pid'],
									'hidden' => $xmlFiles[key($file)]['hidden'],
									'fileid' => $xmlFiles[key($file)]['fileid'],
									'active' => 1,
								));
			
								//remove the active sign for the others in database
								$TYPO3_DB->exec_UPDATEquery('tx_mldbsync_xmlfiles', '', array('active' => '0'));
							}
						
							$this->dbsync->startDatabaseImport($xmlFiles);
							$content = $this->displayImportMessages();
		
						break;
						default:
							$content = $this->drawInputForm();
					}
				} else {
					$content = $LANG->getLL('msg_lockfileFound');
				}

				$this->content.=$this->doc->section($LANG->getLL('function1'),$content,0,1);
			break;
			case 2:
				//try to get variables
				$action = t3lib_div::_POST('action');
				$uid = t3lib_div::_POST('uid');
				
				//perform requested action
				if ($action == 'activate') {
					$TYPO3_DB->exec_UPDATEquery('tx_mldbsync_xmlfiles', 'fileid=\''.$uid.'\'', array('gabriel_enabled' => '1'));
					
				} elseif ($action == 'deactivate') {
					$TYPO3_DB->exec_UPDATEquery('tx_mldbsync_xmlfiles', 'fileid=\''.$uid.'\'', array('gabriel_enabled' => '0'));
					
				}
			
				$this->content.= $this->doc->section($LANG->getLL('function2'), $this->displayXmlForm(),0,1);
			break;
		}
	}
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ml_dbsync/mod1/index.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ml_dbsync/mod1/index.php']);
}


// Make instance:
$SOBE = t3lib_div::makeInstance('tx_mldbsync_module1');
$SOBE->init();

// Include files?
foreach($SOBE->include_once as $INC_FILE)	include_once($INC_FILE);

$SOBE->main();
$SOBE->printContent();

?>
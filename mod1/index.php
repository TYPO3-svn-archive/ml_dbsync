<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2006 Markus Friedrich (markus.friedrich@media-lights.de)
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
 * Module 'DB Sync' for the 'ml_dbsync' extension.
 *
 * @author	Markus Friedrich <markus.friedrich@media-lights.de>
 */


	// DEFAULT initialization of a module [BEGIN]
unset($MCONF);
require ("conf.php");
require ($BACK_PATH."init.php");
require ($BACK_PATH."template.php");
$LANG->includeLLFile("EXT:ml_dbsync/mod1/locallang.php");
#include ("locallang.php");
require_once (PATH_t3lib."class.t3lib_scbase.php");
require_once (PATH_t3lib."class.t3lib_tcemain.php");
$BE_USER->modAccess($MCONF,1);	// This checks permissions and exits if the users has no permission for entry.
	// DEFAULT initialization of a module [END]

class tx_mldbsync_module1 extends t3lib_SCbase {
	var $pageinfo;
	var $tce;
	var $fileFunc;
	var $stdGraphic;
	var $userID;
	var $separator = '|';

	//database
	var $db;
	var $typo_db;
	
	var $validLogEntries = '';
	var $pages = array();
	var $startPID = 0;
	var $inputFieldsXML = 3;
	var $currentPID;
	var $action_deletedItems = 'restore';	//defines how to handle items deleted attribute, possible values: restore, leaveUntouched, createNew
	var $action_hiddenItems = 'update';		//defines how to handle items hidden attribute, possibible values: update, leaveUntouched

	// summary
	var $summary = array();
	var $createdPages = 0;
	var $createdPages_error = 0;
	var $updatedPages = 0;
	var $updatedPages_error = 0;
	var $createdContents = 0;
	var $createdContents_error = 0;
	var $updatedContents = 0;
	var $updatedContents_error = 0;
	
	/**
	 *
	 */
	function init()	{
		global $BE_USER,$LANG,$BACK_PATH,$TCA_DESCR,$TCA,$CLIENT,$TYPO3_CONF_VARS;

		parent::init();

		//set alias to typo3 db
		$this->typo_db =& $GLOBALS['TYPO3_DB'];

		//save the userid
		$this->userID = $BE_USER->user['uid'];

		//create instance of tcemain
		$this->tce = t3lib_div::makeInstance('t3lib_TCEmain');			

		//create file func instance
		$this->fileFunc = t3lib_div::makeInstance('t3lib_basicFileFunctions');
	
		//create t3lib_stdGraphic instance 
		$this->stdGraphic = t3lib_div::makeInstance('t3lib_stdGraphic');
		$this->stdGraphic->init();
		$this->stdGraphic->noFramePrepended = true;
		
			
		//create page template, that is used for all entries in pages
		$pageTemplate = array(
			'crdate' => time(),
			'cruser_id' => $this->userID,
			'tstamp' => time(),
			'perms_userid' => $this->userID,
			'tx_mldbsync_created' => 1,
			'hidden' => 0,
		);
		$this->pageTemplate = array_merge ($this->tce->newFieldArray('pages'), $pageTemplate); 

		
	}

	/**
	 * Adds items to the ->MOD_MENU array. Used for the function menu selector.
	 */
	function menuConfig()	{
		global $LANG;
		$this->MOD_MENU = Array (
			"function" => Array (
				"1" => $LANG->getLL("function1"),
				//"2" => $LANG->getLL("function2"),
				//"3" => $LANG->getLL("function3"),
			)
		);
		parent::menuConfig();
	}

	/**
	 * Main function of the module. Write the content to $this->content
	 */
	function main()	{
		global $BE_USER,$LANG,$BACK_PATH,$TCA_DESCR,$TCA,$CLIENT,$TYPO3_CONF_VARS;
		// Access check!
		// The page will show only if there is a valid page and if this page may be viewed by the user
		$this->pageinfo = t3lib_BEfunc::readPageAccess($this->id,$this->perms_clause);
		$access = is_array($this->pageinfo) ? 1 : 0;

		//if (($this->id && $access) || ($BE_USER->user["admin"] && !$this->id))	{
		if ($BE_USER->user["admin"])	{

				// Draw the header.
			$this->doc = t3lib_div::makeInstance("bigDoc");
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

			$headerSection = $this->doc->getHeader("pages",$this->pageinfo,$this->pageinfo["_thePath"])."<br>".$LANG->sL("LLL:EXT:lang/locallang_core.php:labels.path").": ".t3lib_div::fixed_lgd_pre($this->pageinfo["_thePath"],50);

			$this->content.=$this->doc->startPage($LANG->getLL("title"));
			$this->content.=$this->doc->header($LANG->getLL("title"));
			$this->content.=$this->doc->spacer(5);
			$this->content.=$this->doc->section("",$this->doc->funcMenu($headerSection,t3lib_BEfunc::getFuncMenu($this->id,"SET[function]",$this->MOD_SETTINGS["function"],$this->MOD_MENU["function"])));
			$this->content.=$this->doc->divider(5);


			// Render content:
			$this->moduleContent();


			// ShortCut
			if ($BE_USER->mayMakeShortcut())	{
				$this->content.=$this->doc->spacer(20).$this->doc->section("",$this->doc->makeShortcutIcon("id",implode(",",array_keys($this->MOD_MENU)),$this->MCONF["name"]));
			}

			$this->content.=$this->doc->spacer(10);
		} else {
				// If no access or if ID == zero

			$this->doc = t3lib_div::makeInstance("mediumDoc");
			$this->doc->backPath = $BACK_PATH;

			$this->content.=$this->doc->startPage($LANG->getLL("title"));
			$this->content.=$this->doc->header($LANG->getLL("title"));
			$this->content.=$this->doc->spacer(5);
			$this->content.=$this->doc->spacer(10);
		}
	}

	/**
	 * Prints out the module HTML
	 */
	function printContent()	{

		$this->content.=$this->doc->endPage();
		echo $this->content;
	}

	

	/**
	 * parses the where clause and substitutes fields
	 */
	function parseString($clause){

		while (ereg('.*(##.*##).*', $clause, $reg) !== false) {
			ereg('^##(.*)##$', $reg[1], $varName);
			$val = eval('return $this->'.$varName[1].';');
			$clause = str_replace($reg[1], $val, $clause);
		}

		return($clause);	
	}

	
	/**
	 * creates and executes an sql statement 
	 */
	function execQuery(&$sql){
		$select = $sql['select'];
		$from = $sql['from'];

		if (isset($sql['where'])) {
			$where = $this->parseString($sql['where']);
		} else $where = '';

		if (isset($sql['groupby'])) {
			$groupBy = $sql['groupby'];
		} else $groupBy = '';

		if (isset($sql['orderby'])) {
			$orderBy = $sql['orderby'];
		} else $orderBy = '';

		$RS = $this->db->exec_SELECTquery($select, $from, $where, $groupBy, $orderBy);
		return $RS;
	}

	function checkQuerySuccess(&$db, $mode, $queryType) {
		
		if ($db->sql_affected_rows() > 0) {
			if ($mode == 'page' && $queryType == 'insert') {
				$this->createdPages++;
				
			} elseif ($mode == 'page' && $queryType == 'update') {
				$this->updatedPages++;
				
			} elseif ($mode == 'content' && $queryType == 'insert') {
				$this->createdContents++;
				
			} elseif ($mode == 'content' && $queryType == 'update') {
				$this->updatedContents++;
			} 
		}
		else {
			if ($mode == 'page' && $queryType == 'insert') {
				$this->createdPages_error++;
				
			} elseif ($mode == 'page' && $queryType == 'update') {
				$this->updatedPages_error++;
				
			} elseif ($mode == 'content' && $queryType == 'insert') {
				$this->createdContents_error++;
				
			} elseif ($mode == 'content' && $queryType == 'update') {
				$this->updatedContents_error++;
			} 
		}
		
	}
	
	/**
	 * creates contents
	 */
	function createContent(&$struct, $pid){
		global $TCA;
		
		/* get uploadFolder for images from TCA */
		$uploadFolder = $TCA['tt_content']['columns']['image']['config']['uploadfolder'];
		
		if (isset($struct['sql'])) {
			$RS = $this->execQuery($struct['sql']);
			$currentTableCol = 0;
			$tableRow = '';
			
			$data = array(
				'pid' => $pid,
				'tstamp' => time(),
				'bodytext' => '',
				'image' => '',
				'imagecaption' => '',
				'tx_mldbsync_created' => 1,
			);
			
			//set deleted attribute if demanded
			if ($this->action_deletedItems == 'restore') {
				$data['deleted'] = 0;
			}
			
			/* set CType */
			if (isset($struct['CType'])) {
				$data['CType'] = $struct['CType'];
			} else $data['CType'] = 'text';

			/* set image extension */
			if (isset($struct['imageExtension'])) {
				$ext = $struct['imageExtension'];
			} else $ext = 'jpg';

			/* set number of columns for tables */
			if (isset($struct['tableCols'])) {
				$tableCols = $struct['tableCols'];
			} else $tableCols = 3;


			while ($row = $this->typo_db->sql_fetch_assoc($RS)) {
				$contentID = $row['id'];

				if (!isset($logEntry)) {
					$logEntry = $this->checkLog($pid, 'content', $row['id']);
				
					/* delete old images if this is an update process */
					if (isset($logEntry['image']) && !empty($logEntry['image'])) {
						$this->deleteImages($logEntry['image'], $uploadFolder);
					}
				}
				
				if (isset($row['header'])) {
					$data['header'] = $row['header'];
				}

				if (isset($row['bodytext'])) {
					$data['bodytext'] .= $row['bodytext'];
				}
	
				if ($data['CType'] == 'image' || $data['CType'] == 'textpic') {
					if (isset($row['imagecaption'])) {
						$data['imagecaption'] .= $row['imagecaption'];
					}
	
					if (isset($struct['imageDimensions'])) {
						$dimensions = $struct['imageDimensions'];
					} else $dimensions = "";

					if (isset($row['image']) && !empty($row['image'])) {
						if ($data['image'] != '') { $data['image'] .= ','; }
						$data['image'] .= $this->exportImage("$pid.$ext", $row['image'], $dimensions);
					}

				} elseif ($data['CType'] == 'table') {
					if ($currentTableCol != 0) { $tableRow .= '|'; }

					if (isset($row['imageRow'])) {
						if (isset($struct['imageDimensions'])) {
							$dimensions = $struct['imageDimensions'];
						} else $dimensions = "";

						$tableRow .= '<img src="'.$uploadFolder.'/'.$this->exportImage("$pid.$ext", $row['imageRow'], $dimensions) . '" />';
					}
					else { $tableRow .= $row['row']; }

					$currentTableCol++;

					if ($currentTableCol >= $tableCols) {
						$data['bodytext'] .= $tableRow . "\n";
						$tableRow = "";
						$currentTableCol = 0;
					}
				}


			}
		
			if ($data['CType'] == 'image' || $data['CType'] == 'textpic') {
				if (isset($struct['imageorient'])) {
					$data['imageorient'] = $struct['imageorient'];
				}
				if (isset($struct['imagecols'])) {
					$data['imagecols'] = $struct['imagecols'];
				}
				if (isset($struct['imageborder'])) {
					$data['imageborder'] = $struct['imageborder'];
				}
				if (!isset($data['imagecaption'])) {
					$data['imagecaption'] = $struct['imagecaption'];
				}
			}

			if (isset($struct['parseFunc'])) {
				foreach ($struct['parseFunc'] AS $func) {
					eval('$this->'.$func['function'].'($data["'.$func['field'].'"]);');
				}
			}

			//check if content should be wrapped
			if (isset($struct['wrap'])) {
				$wrap = html_entity_decode($struct['wrap']);
				$wrapParts = explode('|', $wrap);

				$data['bodytext'] = $wrapParts[0] . $data['bodytext'] . $wrapParts[1];
			}


			if ($logEntry === false || ($logEntry['deleted'] &&  $this->action_deletedItems == 'createNew')) {
				if ($logEntry !== false && $this->action_deletedItems == 'createNew') {
					$this->typo_db->exec_DELETEquery("tx_mldbsync_log", "logid='{$logEntry['logid']}'");
				}
				
				$this->typo_db->exec_INSERTquery('tt_content', $data);
				$this->checkQuerySuccess($this->typo_db, 'content', 'insert');

				$this->createLogEntry($this->typo_db->sql_insert_id(), $contentID);
			}
			else {
				$this->typo_db->exec_UPDATEquery('tt_content', "uid='{$logEntry['uid']}'", $data);
				$this->checkQuerySuccess($this->typo_db, 'content', 'update');
			}
		}
		elseif (isset($struct['userFunc'])) {
			$this->startUserfunc($struct['userFunc'], $pid);
		}

	}
	
	/**
	 * creates pages
	 */
	function createPage(&$struct, &$pages, $pid){
		global $TCA;

		if (isset($struct['sql'])) {
			$sqlGiven = true;
			$RS = $this->execQuery($struct['sql']);
		} else $sqlGiven = false;

		//set option "action_deletedItems""
		if (isset($struct['options']['action_deletedItems'])) {
			$this->action_deletedItems = $struct['options']['action_deletedItems'];
		}
		
		while ($sqlGiven && $row = $this->typo_db->sql_fetch_assoc($RS)) {
			$pages = array();
			$logEntry = $this->checkLog($row['id']);

			if (isset($struct['doktype'])) {
				$doktype = $struct['doktype'];
			}

			if (isset($row['sorting'])) {
				$sorting = $row['sorting'];
			} else $sorting = 500;
			

			if (isset($row['media']) && !empty($row['media'])) {
				if ($logEntry !== false) {
					//delete old images
					$this->deleteImages($logEntry['media'], $TCA['pages']['columns']['media']['config']['uploadfolder']);
				}
				
				if (isset($struct['mediaExtension'])) {
					$ext = $struct['mediaExtension'];
				} else $ext = 'jpg';
			
				if (isset($struct['mediaDimensions'])) {
					$dimensions = $struct['mediaDimensions'];
				} else $dimensions = "";

				$media = $this->exportImage($row['title'].".$ext", $row['media'], $dimensions, 'pages');
			} else $media = "";

			$data = array(
				'doktype' => $doktype,
				'title' => $row['title'],
				'media' => $media,
				'pid' => $pid,
				'sorting' => $sorting,
			);
			$data += $this->pageTemplate;
			$pages = array('id' => $row[id]);	
			
			//set deleted attribute if demanded
			if ($this->action_deletedItems == 'restore') {
				$data['deleted'] = 0;
			}
			

			//check if any content should be parsed
			if (isset($struct['parseFunc'])) {
				foreach ($struct['parseFunc'] AS $func) {
					eval('$this->'.$func['function'].'($data["'.$func['field'].'"]);');
				}
			}

			if ($logEntry === false || ($logEntry['deleted'] &&  $this->action_deletedItems == 'createNew')) {
				if ($logEntry !== false && $this->action_deletedItems == 'createNew') {
					$this->typo_db->exec_DELETEquery("tx_mldbsync_log", "logid='{$logEntry['logid']}'");
				}
				
				$this->typo_db->exec_INSERTquery('pages', $data);
				$this->checkQuerySuccess($this->typo_db, 'page', 'insert');
				$newPID = $this->typo_db->sql_insert_id();

				$this->createLogEntry($newPID);

			} else {
				unset($data['crdate']);
				unset($data['cruser_id']);

				$this->typo_db->exec_UPDATEquery('pages', "uid='{$logEntry['uid']}'", $data);
				$this->checkQuerySuccess($this->typo_db, 'page', 'update');
				$newPID = $logEntry['uid'];

			}
			$pages['pid'] = $newPID;


			//insert sub pages if any
			$i = 1;
			if (isset($struct['pages'])) {
				$pages[$i] = array();
				foreach ($struct['pages'] AS $structElement) {
					$this->createPage($structElement, $pages[$i], $newPID);
					$i++;
				}
			}

			//remove links to subpages
			while (list($key, $val) = each($pages)) {
				if (is_int($key)) {
					unset ($pages[$key]);
				}
			}


			//insert contents
			$this->currentPID = $newPID;
			if (isset($struct['contents'])) {
				foreach ($struct['contents'] AS $content) {
					$this->createContent($content, $newPID);
				}
			}

		}
	}


	/**
	 * Imports the database
	 */
	function startUserfunc(&$func, $pid){
		$args = "$pid";
		if (isset($func['args'])) {
			foreach ($func['args'] AS $arg) {
				if (strpos($arg, 'pages') !== false) {
					$args .= ',' . eval('return $this->'.$arg.';');
				} else $args .= ',' . $arg;
			}
		}
		eval ('$this->'.$func['func'].'('.$args.');');
	}


	/**
	 * Imports the database
	 */
	function importDatabase($struct, $pid){
		$this->pages = array();
		$i = 1;

		foreach ($struct AS $structElement) {
			//create page
			$this->createPage($structElement, $this->pages[$i], $pid);
			$i++;
		}
	}
		


	/**
	 * Imports the database
	 */
	function startDatabaseImport(){
		global $LANG;
		
		//delete old hyperlink images
		$this->deleteHyperlinkImages();

		//get xmlFiles from $_POST
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
			$this->typo_db->exec_UPDATEquery('tx_mldbsync_xmlfiles', "", array('active' => '0'));
		}

		//go through all given xmlFiles and perform import
		foreach ($xmlFiles AS $xmlFile) {

			//check if values are complete
			if ($xmlFile['file'] == "" || $xmlFile['pid'] == "") {
				continue;
			}
			//save/update file in database
			if (isset($xmlFile['fileid'])) {
				$id = $xmlFile['fileid'];
				unset($xmlFile['fileid']);
				if (!isset($xmlFile['active'])) { $xmlFile['active'] = 0; }
				$this->typo_db->exec_UPDATEquery('tx_mldbsync_xmlfiles', "fileid='$id'", $xmlFile);
			} else {
				if (!isset($xmlFile['active'])) { $xmlFile['active'] = 0; }
				$this->typo_db->exec_INSERTquery('tx_mldbsync_xmlfiles' ,$xmlFile);
			}
			
			//check if file is activated
			if ($xmlFile['active'] == 0) {
				continue;
			}

			//prepare summary
			$this->summary[basename($xmlFile['file'])] = array(
				'xmlFile' => $LANG->getLL('invalidFile'),
				'database' => $LANG->getLL('notConnectedToDB'),
				'importMode' => $xmlFile['hidden'],
				'createdPages' => 0,
				'updatedPages' => 0,
				'createdContents' => 0,
				'updatedContents' => 0,
				'' => '',
				'createdPages_error' => 0,
				'updatedPages_error' => 0,
				'createdContents_error' => 0,
				'updatedContents_error' => 0,
			);
			$currentEntry =& $this->summary[basename($xmlFile['file'])];
			$this->createdPages =& $currentEntry['createdPages'];
			$this->createdPages_error =& $currentEntry['createdPages_error'];
			$this->updatedPages =& $currentEntry['updatedPages'];
			$this->updatedPages_error =& $currentEntry['updatedPages_error'];
			$this->createdContents =& $currentEntry['createdContents'];
			$this->createdContents_error =& $currentEntry['createdContents_error'];
			$this->updatedContents =& $currentEntry['updatedContents'];
			$this->updatedContents_error =& $currentEntry['updatedContents_error'];

			//set template - determines if pages are imported as hidden or visible
			$this->pageTemplate['hidden'] = $xmlFile['hidden'];

			if (file_exists(PATH_site . trim($xmlFile['file']))) {
				//load the xml file that contains the struct definition
				$struct = t3lib_div::getURL(PATH_site . trim($xmlFile['file']));
				$struct = t3lib_div::xml2array($struct);
			}

			if (is_array($struct)) {
				$this->startPID = trim($xmlFile['pid']);
				$currentEntry['xmlFile'] = $LANG->getLL('validFile');

				//connect to database
				if (isset($struct['database'])) {

					$host = $struct['database']['host'];
					$user = $struct['database']['username'];
					$passwd = $struct['database']['password'];
					$database = $struct['database']['database'];

					if ($this->connectDB($host, $user, $passwd, $database)) {
						$currentEntry['database'] = $LANG->getLL('connectedToDB');

						//include given libraries if given
						if (isset($struct['libraries'])) {
							$this->includeLibraries($struct['libraries']);	
						}

						//import Database
						$this->importDatabase($struct['pages'], $this->startPID);
					} 

					//delete pages that aren't in database any more
		 			$this->deleteOldPages();
				}

			}
		}
	}
	
	

	/**
	 * include Libraries
	 */
	function includeLibraries($libraries){
		foreach ($libraries AS $library) {
			require_once(PATH_site . $library['includeFile']);
			eval ('$this->'.$library['class'].' = t3lib_div::makeInstance('.$library['class'].');');
			eval ('$this->'.$library['class'].'->init($this);');

			$this->userClasses[] = $library['class'];
		}
	}

	
		
	/**
	 * deletes old hyperlink images
	 */
	function deleteHyperlinkImages() {
		$path = $this->tce->destPathFromUploadFolder('uploads');

		$images = "";
		$directory = dir($path);
		while (false !== ($entry = $directory->read())) {
			if (strpos($entry, 'hypertext') !== false) {
				if ($images != '') {
					$images .= ',';
				}

				$images .= $entry;
			}
		}
		$directory->close();

		$this->deleteImages($images, 'uploads');
	}



	
	/**
	 * deletes images
	 */
	function deleteImages($images, $folder) {
		$fullPath = $this->tce->destPathFromUploadFolder($folder);

		$imagesArray = explode(",", $images);
		while (list(, $image) = each($imagesArray)) {
			if (!empty($image) && file_exists($fullPath . '/' . $image)) {
				unlink ($fullPath . '/' . $image);
			}
		}
	}



	/**
	 * exports image 
	 */
	function exportImage($name, $image, $dimensions = '', $table = 'tt_content') {
		global $TCA;

		if (!empty($image)) {
			//get destination path
			if ($table == 'pages') {
				$uploadFolder = $TCA['pages']['columns']['media']['config']['uploadfolder'];
			}
			elseif ($table == 'tt_content') { 
				$uploadFolder = $TCA['tt_content']['columns']['image']['config']['uploadfolder']; 
			}
			else { $uploadFolder = 'uploads'; }
			
			$filename = $this->fileFunc->cleanFileName($name);
			$pathUploadFolder = $this->tce->destPathFromUploadFolder($uploadFolder);
			$theDestFile = $this->fileFunc->getUniqueName($filename, $pathUploadFolder);

			$fp = fopen($theDestFile, 'w');
			fwrite($fp, $image);
			fclose($fp);

			//convert file if dimensions given
			if ($dimensions != '') {
				$orgFile = $theDestFile;
				$convFile = $this->fileFunc->getUniqueName($filename, PATH_site.'typo3temp/pics');
				$this->stdGraphic->imageMagickExec($orgFile, $convFile, "-resize $dimensions");
				t3lib_div::upload_copy_move($convFile, $orgFile);
				t3lib_div::unlink_tempfile(PATH_site.'typo3temp/pics/'.basename($convFile));
			}


			$result = basename($theDestFile);
		} else $result = '';

		return $result;
	}
			
			


	/**
	 * deletes old pages
	 */
	 function deleteOldPages() {
		$cmd = array('pages' => array());

		//get all ids of the old pages that have to be removed
	 	if (!empty($this->validLogEntries)) {
			$notValidEntry_query = $this->typo_db->exec_SELECTquery('id', 'tx_mldbsync_log', "logid NOT IN ({$this->validLogEntries}) AND startPID='{$this->startPID}'");
		}
		else {
			$notValidEntry_query = $this->typo_db->exec_SELECTquery('id', 'tx_mldbsync_log', "startPID = '{$this->startPID}'");
		}

		while ($entry = $this->typo_db->sql_fetch_assoc($notValidEntry_query)) {
			$cmd['pages'][$entry['id']]['delete'] = 1;
		}


		//remove the pages
		$this->tce->deleteTree = 1;
		$this->tce->start(array(), $cmd);
		$this->tce->process_cmdmap();

		//remove the entries from the log
	 	if (!empty($this->validLogEntries)) {
			$this->typo_db->exec_DELETEquery('tx_mldbsync_log', "logid NOT IN ({$this->validLogEntries}) AND startPID = '{$this->startPID}'");
		} else {
			$this->typo_db->exec_DELETEquery('tx_mldbsync_log', "startPID = '{$this->startPID}'");
		}
	}


	/**
	 * checks log if element already exists
	 */
	function checkLog($pid, $type='page', $contentID = '') {
		$finished = false;

		/* build path to page / content */
		$pages = $this->pages;
		$path = "";
		while (!$finished && is_array($pages)) {
			end($pages);
			if (is_int(key($pages))) {
				$pages = $pages[key($pages)];
		
				if ($pages['id'] != '') {
					if ($path != '') { $path .= $this->separator; }
					$path .= $pages['id'];
				}
			} else $finished = true;
		}

		/* add id to path if element is a page */
		if ($type == 'page') {
			if ($path != '') { $path .= $this->separator; }
			$path .= $pid;
		}

		/* check db for this entry */
		if ($type == 'content') {
			$select = "l.logid, c.*";
			$from = "tx_mldbsync_log l, tt_content c";
			$where = "l.id=c.uid AND path='$path' AND startPID='{$this->startPID}' AND contentID='$contentID'";

		}
		else {
			$select = "l.logid, p.*";
			$from = "tx_mldbsync_log l, pages p";
			$where = "l.id=p.uid AND path='$path' AND startPID='{$this->startPID}'";

		}

		$log_query = $this->typo_db->exec_SELECTquery($select, $from, $where);
		$result = $this->typo_db->sql_fetch_assoc($log_query);

		if ($result !== false) {
			if ($this->validLogEntries != '') $this->validLogEntries .= ',';
			$this->validLogEntries .= $result['logid'];
		}

		return $result;
	}

	/**
	 * creates log entry
	 */
	function createLogEntry($uid, $contentID="") {
		$finished = false;

		/* build path to page / content */
		$pages = $this->pages;
		$path = "";
		while (!$finished) {
			end($pages);
			if (is_int(key($pages))) {
				$pages = $pages[key($pages)];
			
				if ($path != '') { $path .= $this->separator; }
				$path .= $pages['id'];
			} else $finished = true;
		}

		/* build data array and insert data into database */
		$data = array(
			'path' => $path,
			'startPID' => $this->startPID,
			'id' => $uid,
			'contentID' => $contentID,
		);
		$this->typo_db->exec_INSERTquery('tx_mldbsync_log', $data);

		/* add log entry to the "validLogEntries" */
		if ($this->validLogEntries != '') $this->validLogEntries .= ',';
		$this->validLogEntries .= $this->typo_db->sql_insert_id();
	}



	/**
	 * Connect to the database that contains the page/content data
	 */
	function connectDB($host, $username, $password, $database){

		//create db object for the content database, unfortunately the link has to be established without
		//typo3 functions - sql_pconnect would return the old connection if the same server is choosen
		$this->db = t3lib_div::makeInstance('t3lib_db');			
		//$this->db->link = mysql_connect(TYPO3_db_host, TYPO3_db_username, TYPO3_db_password);

		if ($this->db->link = @mysql_connect($host, $username, $password)) {
			if ($this->db->sql_select_db($database)) {
				$result = true;
			} else $result = false;
		} else { $result = false; }	

		return $result;
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
			'<a href="#" onclick="'."this.blur(); vHWin=window.open('$linkToScript',''," . 
			"'height=300,width=500,status=0,menubar=0,scrollbars=1');" . 
			'vHWin.focus();return false;">' . 
			'<img src="'.$this->doc->backPath.'t3lib/gfx/link_popup.gif" />' .
			'</a>' . "\n";

		return $link;
	}


	/**
	 * Generates the input form
	 */
	function drawInputForm(){
		global $LANG;

		//check if there are saved fields, else display empty fields
		if (is_null($xmlFiles = t3lib_div::_POST('xmlFiles'))) {
			$xmlFiles = array();
			$files = $this->typo_db->exec_SELECTquery('*', 'tx_mldbsync_xmlfiles', '');
			while ($file = $this->typo_db->sql_fetch_assoc($files)) {
				$xmlFiles[] = $file;
			}
			
			if (($inputFieldsXML = $this->typo_db->sql_num_rows($files) + 2) == 2) {
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
			$this->typo_db->exec_DELETEquery('tx_mldbsync_xmlfiles', 'fileid="'.$xmlFiles[key($removeField)]['fileid'].'"'); 
			unset($xmlFiles[key($removeField)]);
		}
		
		
		$content=
			'<form name="editform" action="'.t3lib_div::linkThisScript().'" method="post">' . "\n".
			'<input type="hidden" name="action" value="startImport">' . "\n".
			'<input type="hidden" name="inputFieldsXML" value="'.$inputFieldsXML.'">' . "\n".

			'<table>' . "\n" .
			'<tr>' . "\n" .
			'<td>&nbsp;</td>' . "\n" .
			'<td colspan="2">'.$LANG->getLL('xmlFile').'</td>' . "\n" .
			'<td colspan="2">'.$LANG->getLL('pid').'</td>' . "\n" .
			'<td>' . $LANG->getLL('importMode') . '</td>' .
			'<td>&nbsp;</td>' . "\n" .
			'<td>&nbsp;</td>' . "\n" .
			'<td>&nbsp;</td>' . "\n" .
			'</tr>' . "\n";

		for ($i=0; $i<$inputFieldsXML; $i++) {
			if (isset($xmlFiles[$i])) {
				$file = $xmlFiles[$i]['file'];
				$pid = $xmlFiles[$i]['pid'];
				
				if ($xmlFiles[$i]['hidden'] == 0) {
					$selected = ' selected="selected"';
				} else $selected = "";

				if ($xmlFiles[$i]['active'] == 1) {
					$checked = ' checked="checked"';
				} else $checked = "";

				if (isset($xmlFiles[$i]['fileid'])) {
					$hint = $LANG->getLL('hint_update');
					$delBtn = '<input type="submit" name="removeField['.$i.']" value="'.$LANG->getLL('removeFile').'"/>';
					$importBtn = '<input type="submit" name="importFile['.$i.']" value="'.$LANG->getLL('importFile').'"/>';
				} else {
					$hint = $LANG->getLL('hint_import'); 
					$delBtn = "";
					$importBtn = "";
				}

			} else {
				$file = "";
				$pid = "";
				$hidden = 1;
				$checked = "";
				$hint = $LANG->getLL('hint_import');
				$delBtn = "";
				$importBtn = "";
			}
			
			$content .=
				'<tr>' . "\n" .
				'<td><input type="checkbox" name="xmlFiles['.$i.'][active]" value="1"'.$checked.'/></td>' . "\n" .
				'<td><input type="text" name="xmlFiles['.$i.'][file]" value="'.$file.'" /></td>'."\n" .
				'<td>'.$this->createLinkToBrowseLinks('file','editform',"xmlFiles[$i][file]").'</td>'."\n" .
				'<td><input type="text" name="xmlFiles['.$i.'][pid]" value="'.$pid.'" /></td>'."\n" .
				'<td>'.$this->createLinkToBrowseLinks('page', 'editform', "xmlFiles[$i][pid]").'</td>'."\n" .
				'<td><select name="xmlFiles['.$i.'][hidden]" >' . "\n" .
				"\t".'<option value="1">'.$LANG->getLL('importPagesAsHidden').'</option>'."\n" .
				"\t".'<option value="0"'.$selected.'>'.$LANG->getLL('importPagesAsVisible').'</option>'."\n" .
				'</select>' . "\n" .
				'<td>'.$hint.'</td>' . "\n" .
				'<td>'.$delBtn.'</td>' . "\n" .
				'<td>'.$importBtn.'</td>' . "\n" .
				'</tr>' . "\n";

			if (isset($xmlFiles[$i]['fileid'])) {
				$content .= '<input type="hidden" name="xmlFiles['.$i.'][fileid]" value="'.$xmlFiles[$i]['fileid'].'" />' . "\n";
			}

		}

		$content .=
			'<tr>' . "\n" .
			'<td colspan="8">' . "\n" .
			'<input type="submit" name="addFields" value="'.$LANG->getLL('addFields').'" />' . "\n" . 
			'<input type="submit" value="'.$LANG->getLL('submitBtn').'" />' . "\n" . 
			'</td>' . "\n" .
			'</tr>' . "\n" .

			'</table>' . "\n" . 
			'</form>' . "\n";

		
		return $content;
	}


	/**
	 * Generates the input form
	 */
	function displayImportMessages() {
		global $LANG;

		while (list($file, $data) = each($this->summary)) {
			$content .=
				"<b>$file</b>" . "<br />\n" .
				'<table>' . "\n";

			while (list($label, $val) = each($data)) {
				if ($label == 'importMode') {	
					if ($val == 0) {
						$val = $LANG->getLL('importPagesAsVisible');
					}
					else { $val = $LANG->getLL('importPagesAsHidden'); }
				}

				$content .= 
					'<tr>' . "\n" .
					'<td>' . $LANG->getLL($label) . '<td>' . "\n" .
					'<td>' . $val . '</td>' . "\n" .
					'</tr>' . "\n";
			}

			$content .=
				'</tr>' . "\n" .
				'</table><br />' . "\n";

		}


		if (empty($this->summary)) {
			$content .= $LANG->getLL('nothingToImport') . "\n";
		}

		$content .=
			'<form action="'.t3lib_div::linkThisScript().'" method="get">' . "\n" .
			'<input type="submit" value="'.$LANG->getLL('btn_back').'" />' . "\n" .
			'</form>' . "\n";

		return $content;
	}


	/**
	 * Locks/Unlocks the typo3 users
	 */
	function lockUsers($lockUsers)	{
		if ($lockUsers) {
			$data = array(
				'disable' => 2
			);
		} else {
			$data = array(
				'disable' => 0
			);
		}
		
		$this->typo_db->exec_UPDATEquery("be_users", "uid <> '".$this->userID."'", $data);
	}


	/**
	 * Generates the module content
	 */
	function moduleContent()	{
		global $LANG, $TYPO3_CONF_VARS;
	
		$cfg = unserialize($TYPO3_CONF_VARS["EXT"]["extConf"]["ml_dbsync"]);	

		//ensure that the import will not be chancelled by the user or by a lack of execution time
		ignore_user_abort(1);
		set_time_limit(0);

		switch((string)$this->MOD_SETTINGS["function"])	{
			case 1:
				$action = t3lib_div::_POST('action');
				$addFields = t3lib_div::_POST('addFields');
				$removeField = t3lib_div::_POST('removeField');

				//check if only fields should be added
				if (!is_null($addFields) || !is_null($removeField)) {
					$action = "";
				}
				
				/* perform required actions */
				switch ($action) {
					case "startImport":
						if ($cfg['lockUsers']) { $this->lockUsers(true); }
						$this->startDatabaseImport();
						if ($cfg['lockUsers']) { $this->lockUsers(false); }
						$action = "displayImportMessages";

						
					break;
				}

				/*  build content in dependence of required action */
				if ($action == "displayImportMessages") {
					$content = $this->displayImportMessages();

					//delete caches
					$this->tce->clear_cacheCmd('all');
				}
				else {
					$content = $this->drawInputForm();
				}

				$this->content.=$this->doc->section($LANG->getLL('title'),$content,0,1);
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

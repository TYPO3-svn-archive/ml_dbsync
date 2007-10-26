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
 * 'DB Sync' class
 *
 * @author	Markus Friedrich <markus.friedrich@media-lights.de>
 * @package TYPO3
 * @subpackage tx_dbsync
 */

require_once (PATH_t3lib.'class.t3lib_scbase.php');
require_once (PATH_t3lib.'class.t3lib_tcemain.php');

class tx_mldbsync {
	var $action_deletedItems = 'restore';	//defines how to handle items deleted attribute, possible values: restore, leaveUntouched, createNew
	var $action_hiddenItems = 'update';	//defines how to handle items hidden attribute, possibible values: update, leaveUntouched
	var $cfg;
	var $currentPID;
	var $fileFunc;
	var $inputFieldsXML = 3;
	var $lastOriginalContentUID = 0;
	var $lockfile;
	var $logfile = false;
	var $pageinfo;
	var $pages = array();
	var $pathUploadFolder = '';
	var $separator = '|';
	var $startPID = 0;
	var $stdGraphic;
	var $tce;
	var $userID;
	var $useTemplaVoila = false;
	var $validLogEntries = '';

	//databases
	var $db;
	var $typo_db;

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
	 * PHP4 wrapper function for the class constructor
	 * 
	 * @return 	void
	 */
	function tx_mldbsync() {
		$this->__construct();
	} // end of "tx_mldbsync() {...}"



	/**
	 * class constructor
	 * 
	 * @return void
	 */
	function __construct()	{
		global $BE_USER, $TYPO3_CONF_VARS, $LANG;

		//set alias to typo3 db
		$this->typo_db =& $GLOBALS['TYPO3_DB'];
		$this->typo_db->debugOutput = true;

		//save the userid
		$this->userID = $BE_USER->user['uid'];

		//create instance of tcemain
		$this->tce = t3lib_div::makeInstance('t3lib_TCEmain');
		$this->tce->start(array(), array());

		//set path to dbsync folder
		$this->pathUploadFolder = $this->tce->destPathFromUploadFolder('uploads/tx_mldbsync');
		
		//set lockfile
		$this->lockfile = PATH_site.'typo3temp/tx_mldbsync.lock';

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

		//get configuration	
		$this->cfg = unserialize($TYPO3_CONF_VARS['EXT']['extConf']['ml_dbsync']);
		
		
		//initialize templa voila if demanded
		if ($this->cfg['useTemplaVoila']) {
			$this->useTemplaVoila = true;
			
			// include TemplaVoila API
			require_once (t3lib_extMgm::extPath('templavoila').'class.tx_templavoila_api.php');
		
			//make instance	
			$apiClassName = t3lib_div::makeInstanceClassName('tx_templavoila_api');
			$this->tv_api = new $apiClassName ('pages');
		}
		
		
		//include labels (create lang object if not present)
		if (!is_object($LANG)) {
			require_once(t3lib_extMgm::extPath('lang', 'lang.php'));
			$LANG = t3lib_div::makeInstance('language');
			$LANG->init('default');
		}
		$LANG->includeLLFile('EXT:ml_dbsync/mod1/locallang.php');
		
	} // end of "__construct() {...}"
	


	/**
	 * Check if a valid lockfile is present 
	 * 
	 * @return	integer		0 = no lockfile, 1 = old lockfile, 2 = new lockfile, import running
	 */
	function checkLockfile() {
		$ret = 0;
		
		if (@file_exists($this->lockfile)) {
			if (filemtime($this->lockfile) < strtotime('-1 day')) {
				$ret = 1;
			} else {
				$ret = 2;
			}
		} 
		
		return $ret;
	} // end of 'function checkLockfile() {..}'



	/**
	 * checks log if element already exists
	 * 
	 * @param	integer		$pid
	 * @param	string		$type: content, pages_language_overlay, page
	 * @param	string		$contentID
	 * @param	integer		$sys_language_uid
	 * @return	mixed		false if no log entry is found, dataset if entry is found
	 */
	function checkLog($pid, $type='page', $contentID = '', $sys_language_uid = 0) {
		$finished = false;

		/* build path to page / content */
		$pages = $this->pages;
		$path = '';
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
			$select = 'tx_mldbsync_log.logid, tt_content.*';
			$from = 'tx_mldbsync_log INNER JOIN tt_content ON (tx_mldbsync_log.id=tt_content.uid)';
			$where = sprintf('tx_mldbsync_log.path=%s AND tx_mldbsync_log.startPID=%s AND tx_mldbsync_log.contentID=%s AND tx_mldbsync_log.sys_language_uid=%s',
				$this->typo_db->fullQuoteStr($path, 'tx_mldbsync_log'),
				$this->typo_db->fullQuoteStr($this->startPID, 'tx_mldbsync_log'),
				$this->typo_db->fullQuoteStr($contentID, 'tx_mldbsync_log'),
				$this->typo_db->fullQuoteStr($sys_language_uid, 'tx_mldbsync_log')
			);

		}
		elseif ($type == 'pages_language_overlay') {
			$select = 'tx_mldbsync_log.logid, pages_language_overlay.*';
			$from = 'tx_mldbsync_log INNER JOIN pages_language_overlay ON (tx_mldbsync_log.id=pages_language_overlay.uid)';
			$where = sprintf('tx_mldbsync_log.path=%s AND tx_mldbsync_log.startPID=%s AND tx_mldbsync_log.contentID=%s',
				$this->typo_db->fullQuoteStr($path, 'tx_mldbsync_log'),
				$this->typo_db->fullQuoteStr($this->startPID, 'tx_mldbsync_log'),
				$this->typo_db->fullQuoteStr($contentID, 'tx_mldbsync_log')
			);
			
		} else {
			$select = 'tx_mldbsync_log.logid, pages.*';
			$from = 'tx_mldbsync_log INNER JOIN pages ON (tx_mldbsync_log.id=pages.uid)';
			$where = sprintf('tx_mldbsync_log.path=%s AND tx_mldbsync_log.startPID=%s  AND tx_mldbsync_log.contentID=%s',
				$this->typo_db->fullQuoteStr($path, 'tx_mldbsync_log'),
				$this->typo_db->fullQuoteStr($this->startPID, 'tx_mldbsync_log'),
				$this->typo_db->fullQuoteStr('', 'tx_mldbsync_log')
			);

		}

		$log_query = $this->typo_db->exec_SELECTquery($select, $from, $where);
		$result = $this->typo_db->sql_fetch_assoc($log_query);

		if ($result !== false) {
			if ($this->validLogEntries != '') $this->validLogEntries .= ',';
			$this->validLogEntries .= $result['logid'];
		}

		return $result;
	} // end of "checkLog() {...}"



	/**
	 * checks if a executed query succeded
	 * 
	 * @param	object		$db: database object
	 * @param	string		$mode
	 * @param	string		$queryType
	 * @return void
	 */
	function checkQuerySuccess($mode, $queryType) {
		if ($mode == 'tt_content') {
			$mode = 'content';
		} elseif ($mode == 'pages') {
			$mode = 'page';
		}
		
		if ($this->typo_db->sql_affected_rows() > 0) {
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
		
	} // end of "checkQuerySuccess() {...}"



	/**
	 * Connect to the database that contains the page/content data
	 * 
	 * @param	string		$host
	 * @param	string		$username
	 * @param	string		$password
	 * @param	string		$database
	 * @return	boolean		true on success else returns false
	 */
	function connectDB($host, $username, $password, $database){

		//create db object for the content database, unfortunately the link has to be established without
		//typo3 functions - sql_pconnect would return the old connection if the same server is choosen
		$this->db = t3lib_div::makeInstance('t3lib_db');			

		if ($this->db->link = @mysql_connect($host, $username, $password, true)) {
			if ($this->db->sql_select_db($database)) {
				$result = true;
			} else {
				$result = false;
			}
		} else { 
			$result = false; 
		}	

		return $result;
	} // end of "connectDB() {...}"



	/**
	 * creates contents
	 * 
	 * @param	array		$struct: xml structure
	 * @param	integer		$pid: current pid
	 * @return	void
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
		
		
			/* set language */
			if (isset($struct['sys_language_uid'])) {
				$data['sys_language_uid'] = $struct['sys_language_uid'];
			} else { $data['sys_language_uid'] = 0; }
		
			/* set parent object if this is a translation */
			if ($struct['sys_language_uid'] != 0) {
				$data['l18n_parent'] = $this->lastOriginalContentUID;
			} else { $data['l18n_parent'] = 0; }
			
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
					$logEntry = $this->checkLog($pid, 'content', $row['id'], $data['sys_language_uid']);
				
					/* delete old images if this is an update process */
					if (isset($logEntry['image']) && !empty($logEntry['image'])) {
						$this->deleteImages($logEntry['image'], $uploadFolder);
					}
				}
				
				//set sorting
				if (isset($row['sorting'])) {
					$sorting = $row['sorting'];
				} else $sorting = 500;
			
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
					} else $dimensions = '';

					if (isset($row['image']) && !empty($row['image'])) {
						if ($data['image'] != '') { $data['image'] .= ','; }
						$data['image'] .= $this->exportImage($pid.'.'.$ext, $row['image'], $dimensions);
					}

				} elseif ($data['CType'] == 'table') {
					if ($currentTableCol != 0) { $tableRow .= '|'; }

					if (isset($row['imageRow'])) {
						if (isset($struct['imageDimensions'])) {
							$dimensions = $struct['imageDimensions'];
						} else $dimensions = '';

						$tableRow .= '<img src="'.$uploadFolder.'/'.$this->exportImage($pid.'.'.$ext, $row['imageRow'], $dimensions) . '" />';
					}
					else { $tableRow .= $row['row']; }

					$currentTableCol++;

					if ($currentTableCol >= $tableCols) {
						$data['bodytext'] .= $tableRow . chr(10);
						$tableRow = '';
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
					eval('$this->'.$func['function'].'($data[\''.$func['field'].'\']);');
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
					$this->exec_DELETEquery('tx_mldbsync_log', 'logid='.$this->typo_db->fullQuoteStr($logEntry['logid'], 'tx_mldbsync_log'));
				}
				
				$sql_insert_id = $this->exec_INSERTquery('tt_content', $data);
				//$this->checkQuerySuccess($this->typo_db, 'content', 'insert');
				
				//store UID if this is an original content element and no translation
				if ($data['sys_language_uid'] == 0) {
					$this->lastOriginalContentUID = $sql_insert_id;
				}

				$this->createLogEntry($sql_insert_id, $contentID, $data['sys_language_uid']);
			}
			else {
				$this->exec_UPDATEquery('tt_content', 'uid='.$this->typo_db->fullQuoteStr($logEntry['uid'], 'tt_content'), $data, true, $logEntry['uid']);
				
				//store UID if this is an original content element and no translation
				if ($data['sys_language_uid'] == 0) {
					$this->lastOriginalContentUID = $logEntry['uid'];
				}
			}
		}
		elseif (isset($struct['userFunc'])) {
			$this->startUserfunc($struct['userFunc'], $pid);
		}

	} // end of "createContent() {...}"



	/**
	 * creates log entry
	 * 
	 * @param	integer		$uid
	 * @param	string		$contentID
	 * @param	integer		$sys_language_uid
	 */
	function createLogEntry($uid, $contentID='', $sys_language_uid = 0) {
		$finished = false;

		/* build path to page / content */
		$pages = $this->pages;
		$path = '';
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
			'sys_language_uid' => $sys_language_uid
		);
		$sql_insert_id = $this->exec_INSERTquery('tx_mldbsync_log', $data);

		/* add log entry to the "validLogEntries" */
		if ($this->validLogEntries != '') $this->validLogEntries .= ',';
		$this->validLogEntries .= $sql_insert_id;
	} // end of "createLogEntry() {...}"



	/**
	 * creates page
	 * 
	 * @param	array		$struct: xml structure
	 * @param	array		$pages
	 * @param	integer		$pid: current pid
	 * @return	void
	 */
	function createPage(&$struct, &$pages, $pid){
		global $TCA;

		if (isset($struct['sql'])) {
			$sqlGiven = true;
			$RS = $this->execQuery($struct['sql']);
		} else $sqlGiven = false;

		//set option "action_deletedItems"
		if (isset($struct['options']['action_deletedItems'])) {
			$this->action_deletedItems = $struct['options']['action_deletedItems'];
		}
		
		while ($sqlGiven && $row = $this->db->sql_fetch_assoc($RS)) {
			$pages = array();
			$logEntry = $this->checkLog($row['id']);

			//set doktype
			if (isset($struct['doktype'])) {
				$doktype = $struct['doktype'];
			}
			
			//set sorting
			if (isset($row['sorting'])) {
				$sorting = $row['sorting'];
			} else $sorting = 500;
			
			//set media if demanded
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
				} else $dimensions = '';

				$media = $this->exportImage($row['title'].'.'.$ext, $row['media'], $dimensions, 'pages');
			} else $media = '';

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
			

			//check if content should be parsed
			if (isset($struct['parseFunc'])) {
				foreach ($struct['parseFunc'] AS $func) {
					eval('$this->'.$func['function'].'($data[\''.$func['field'].'\']);');
				}
			}

			//insert / update page record
			if ($logEntry === false || ($logEntry['deleted'] &&  $this->action_deletedItems == 'createNew')) {
				if ($logEntry !== false && $this->action_deletedItems == 'createNew') {
					$this->exec_DELETEquery('tx_mldbsync_log', 'logid='.$this->typo_db->fullQuoteStr($logEntry['logid'], 'tx_mldbsync_log'));
				}
				
				$newPID = $this->exec_INSERTquery('pages', $data);

				$this->createLogEntry($newPID);

			} else {
				unset($data['crdate']);
				unset($data['cruser_id']);

				$this->exec_UPDATEquery('pages', 'uid='.$this->typo_db->fullQuoteStr($logEntry['uid'], 'pages'), $data);
				$newPID = $logEntry['uid'];

			}
			$pages['pid'] = $newPID;


			//insert alternative page languages if set
			if (isset($struct['alternative_page_language'])) {
				foreach ($struct['alternative_page_language'] AS $lang) {
					$title = '';
					
					if (isset($lang['sys_language_uid'])) {
						$sys_language_uid =  $lang['sys_language_uid'];	
					} else { $sys_language_uid = 0; }
					
					//get log data
					$logEntry = $this->checkLog($newPID, 'pages_language_overlay', 'L'.$sys_language_uid);
					
					if (isset($lang['title'])) {
						$title = $lang['title'];
					} elseif (isset($lang['sql'])) {
						$RS_apl = $this->execQuery($lang['sql']);
						if ($row_RS = $this->db->sql_fetch_assoc($RS_apl)) {
							$title = $row_RS['title'];
						}
						
					}
					
					//prepare data array	
					$lang_data = array(
						'pid' => $newPID,
						'tstamp' => time(),
						'crdate' => time(),
						'cruser_id' => $this->userID,
						'sys_language_uid' => $sys_language_uid,
						'title' => $title,
						'hidden' => 0,
					);
					
					//check if the parse func from the original element should be used
					if (isset($lang['use_parent_parsefunc']) && $lang['use_parent_parsefunc'] && isset($struct['parseFunc'])) {
						foreach ($struct['parseFunc'] AS $func) {
							eval('$this->'.$func['function'].'($lang_data[\''.$func['field'].'\']);');
						}
					} elseif (isset($lang['parseFunc'])) {
						foreach ($lang['parseFunc'] AS $func) {
							eval('$this->'.$func['function'].'($lang_data[\''.$func['field'].'\']);');
						}
					}
	
					if (!empty($lang_data['title'])) {	
						//set media if demanded
						if (isset($lang['use_parent_media']) && $lang['use_parent_media']) {
							$lang_data['media'] = $media;
						
						} elseif (isset($row_RS['media']) && !empty($row_RS['media'])) {
							if ($logEntry !== false) {
								//delete old images
								$this->deleteImages($logEntry['media'], $TCA['pages']['columns']['media']['config']['uploadfolder']);
							}
						
							if (isset($lang['mediaExtension'])) {
								$ext = $lang['mediaExtension'];
							} else $ext = 'jpg';
						
							if (isset($lang['mediaDimensions'])) {
								$dimensions = $lang['mediaDimensions'];
							} else $dimensions = '';
		
							$lang_data['media'] = $this->exportImage($row_RS['title'].'.'.$ext, $row_RS['media'], $dimensions, 'pages');
						
						}
	
				
				
						//insert / update alternative page language
						if ($logEntry === false || ($logEntry['deleted'] &&  $this->action_deletedItems == 'createNew')) {
							if ($logEntry !== false && $this->action_deletedItems == 'createNew') {
								$this->exec_DELETEquery('tx_mldbsync_log', 'logid='.$this->typo_db->fullQuoteStr($logEntry['logid'], 'tx_mldbsync_log'));
							}
							
							$APL_pid = $this->exec_INSERTquery('pages_language_overlay', $lang_data);
			
							$this->createLogEntry($APL_pid, 'L'.$sys_language_uid);
							
						} else {
							$this->exec_UPDATEquery('pages_language_overlay', 'uid='.$this->typo_db->fullQuoteStr($logEntry['uid'], 'pages_language_overlay'), $lang_data);
						}
					} elseif ($logEntry !== false) {
					//an alternative page language was not found => remove old alternative page language
							$this->exec_DELETEquery('pages_language_overlay', 'uid='.$this->typo_db->fullQuoteStr($logEntry['uid'], 'pages_language_overlay'));
							$this->exec_DELETEquery('tx_mldbsync_log', 'logid='.$this->typo_db->fullQuoteStr($logEntry['logid'], 'tx_mldbsync_log'));
					}
				}
			}
			

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
	} // end of "createPage() {...}"



	/**
	 * deletes old hyperlink images
	 * 
	 * @return	void
	 */
	function deleteHyperlinkImages() {
		$path = $this->tce->destPathFromUploadFolder('uploads');

		$images = '';
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
	} // end of "deleteHyperlinkImages() {...}"



	/**
	 * deletes images
	 * 
	 * @param	string		$images: list of images (test.jpg, test2.jpg)
	 * @param	string		$folder: image folder e.g. uploads
	 * @return	void
	 */
	function deleteImages($images, $folder) {
		$fullPath = $this->tce->destPathFromUploadFolder($folder);

		$imagesArray = explode(',', $images);
		while (list(, $image) = each($imagesArray)) {
			$image = trim($image);
			
			if (!empty($image) && file_exists($fullPath . '/' . $image)) {
				unlink ($fullPath . '/' . $image);
			}
		}
	} // end of "deleteImages() {...}"



	/**
	 * deletes old pages
	 * 
	 * @return	void
	 */
	 function deleteOldPages() {
		$cmd = array('pages' => array());

		//get all ids of the old pages that have to be removed
	 	if (!empty($this->validLogEntries)) {
			$notValidEntry_query = $this->typo_db->exec_SELECTquery(
				'id', 
				'tx_mldbsync_log',
				sprintf('logid NOT IN (%s) AND startPID=%s AND contentID=%s',
					$this->validLogEntries,
					$this->typo_db->fullQuoteStr($this->startPID, 'tx_mldbsync_log'),
					$this->typo_db->fullQuoteStr('', 'tx_mldbsync_log')
				),
				'',
				'id DESC'
			);
		} else {
			$notValidEntry_query = $this->typo_db->exec_SELECTquery(
				'id',
				'tx_mldbsync_log',
				sprintf('startPID=%s AND contentID=%s',
					$this->typo_db->fullQuoteStr($this->startPID, 'tx_mldbsync_log'),
					$this->typo_db->fullQuoteStr('', 'tx_mldbsync_log')
				),
				'',
				'id DESC'
			);
		}

		//build command array for the tce
		while ($entry = $this->typo_db->sql_fetch_assoc($notValidEntry_query)) {
			$cmd['pages'][$entry['id']]['delete'] = 1;
		}

	
		//log action
		$this->logAction('deleteOldPages', 'pages', $this->typo_db->sql_num_rows($notValidEntry_query), $cmd);


		//remove the pages
		$this->tce->deleteTree = 1;
		$this->tce->start(array(), $cmd);
		$this->tce->process_cmdmap();

		//remove the entries from the log
	 	if (!empty($this->validLogEntries)) {
			$this->exec_DELETEquery(
				'tx_mldbsync_log',
				sprintf('logid NOT IN (%s) AND startPID=%s',
					$this->validLogEntries,
					$this->typo_db->fullQuoteStr($this->startPID, 'tx_mldbsync_log')
				)
			);
		} else {
			$this->exec_DELETEquery(
				'tx_mldbsync_log', 
				'startPID='.$this->typo_db->fullQuoteStr($this->startPID, 'tx_mldbsync_log')
			);
		}
	} // end of "deleteOldPages() {...} "



	/**
	 * executes a delete query
	 * 
	 * @param	string		$table: tablename
	 * @param	string		$where: where clause
	 */
	 function exec_DELETEquery($table, $where) {
	 	$this->typo_db->exec_DELETEquery($table, $where);
	 } // end of "exec_DELETEquery() {...}"



	/**
	 * executes an insert query
	 * 
	 * @param	string		$table: tablename
	 * @param	array		$data: field values as key=>value pairs
 	 * @param	boolean		$checkQuerySuccess
	 * @return	integer		sql insert id
	 */
	 function exec_INSERTquery($table, $data, $checkQuerySuccess = true) {


		//insert record
	 	$this->typo_db->exec_INSERTquery($table, $data);
		$sql_insert_id = $this->typo_db->sql_insert_id();
	 	if ($checkQuerySuccess) {
		 	$this->checkQuerySuccess($table, 'insert');
	 	}

	 	$this->logAction('INSERT', $table, $this->typo_db->sql_affected_rows());
	 	
	 	
	 	//create reference for TemplaVoila
	 	if ($this->useTemplaVoila && $table == 'tt_content') {

			//get position of this element
			$posData = $this->getTvPosition($data['pid'], $sql_insert_id);
			
			$destinationPointer = array(
				'table' => 'pages',
				'uid' => $data['pid'],
				'sheet' => 'sDEF',
				'sLang' => 'lDEF',
				'field' => 'field_content',
				'vLang' => 'vDEF',
				'position' => $posData['position'],
				'targetCheckUid' => $posData['targetCheckUid']
			);
	 		$this->tv_api->referenceElementByUid ($this->typo_db->sql_insert_id(), $destinationPointer);
	 	}
	 	
	 	return $sql_insert_id;
	 } // end of "exec_INSERTquery() {...}"
	


	 
	/**
	 * executes an update query
	 * 
	 * @param	string		$table: tablename
 	 * @param	string		$where: where clause
 	 * @param	boolean		$checkQuerySuccess
	 * @param	boolean		$uid: uid of the updated element (only needed for TemplaVoila support)
	 * @param	array		$data: field values as key=>value pairs
	 */
	 function exec_UPDATEquery($table, $where, $data, $checkQuerySuccess = true, $uid = 0) {

		//remove sorting
		if (isset($data['sorting'])) {
			//unset($data['sorting']);
		}

		//update record
	 	$this->typo_db->exec_UPDATEquery($table, $where, $data);
	 	if ($checkQuerySuccess) {
		 	$this->checkQuerySuccess($table, 'update');
	 	}
	
	 	
	 	$this->logAction('UPDATE', $table, $this->typo_db->sql_affected_rows());
		//update reference for TemplaVoila
	 	if ($this->useTemplaVoila && $table == 'tt_content') {
			$pointer = $this->tv_api->flexform_getPointersByRecord($uid, $data['pid']);
			if (empty($pointer)) {
				//get position of this element
				$posData = $this->getTvPosition($data['pid'], $uid);
			
				$destinationPointer = array(
					'table' => 'pages',
					'uid' => $data['pid'],
					'sheet' => 'sDEF',
					'sLang' => 'lDEF',
					'field' => 'field_content',
					'vLang' => 'vDEF',
					'position' => $posData['position'],
					'targetCheckUid' => $posData['targetCheckUid']
				);
				$this->tv_api->referenceElementByUid ($uid, $destinationPointer);
			}
			
			
		}

	 } // end of "exec_UPDATEquery() {...}"



	/**
	 * creates and executes an sql statement defined in xml structure
	 * 
	 * @param	array		$sql
	 * @return	resource		sql result set
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
	} // end of "execQuery() {...}"



	/**
	 * exports image 
	 * 
	 * @param	string		$name
	 * @param	string		$image: image content
	 * @param	string		$dimensions: e.g. 80x80
	 * @param	string		$table: tt_content, pages, etc
	 * @return	string		filename of the exported image
	 */
	function exportImage($name, $image, $dimensions = '', $table = 'tt_content') {
		global $TCA;
		$result = '';

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

			if ($fp = @fopen($theDestFile, 'wb')) {
				fwrite($fp, $image);
				fclose($fp);

				//convert file if dimensions given
				if ($dimensions != '') {
					$orgFile = $theDestFile;
					$convFile = $this->fileFunc->getUniqueName($filename, PATH_site.'typo3temp/pics');
					$this->stdGraphic->imageMagickExec($orgFile, $convFile, '-resize '.$dimensions);
					t3lib_div::upload_copy_move($convFile, $orgFile);
					t3lib_div::unlink_tempfile(PATH_site.'typo3temp/pics/'.basename($convFile));
				}
				
				$result = basename($theDestFile);
			}
		} 

		return $result;
	} // end of "exportImage() {...}"



	/**
	 * Determines the templa voila position
	 * 
	 * @param	integer		$pid: page ID
	 * @param	integer		$uid: content ID
	 * @return	array		position data
	 */
	function getTvPosition($pid, $uid) {
		//initialize positionData
		$ret = array(
			'position' => 0,
			'targetCheckUid' => ''
		);

		//find parent record
		$RS = $this->typo_db->exec_SELECTquery(
			'uid',
			'tt_content',
			sprintf('deleted=%s AND pid=%s',
				$this->typo_db->fullQuoteStr('0', 'tt_content'),
				$this->typo_db->fullQuoteStr($pid,  'tt_content')
			),
			'',
			'sorting ASC'
		);
		

		if ($this->typo_db->sql_num_rows($RS) > 1) {
			$parentRecord = $uid;

			//get parent records
			$parentRecords = array();
			while ($row = $this->typo_db->sql_fetch_assoc($RS)) {
				if ($row['uid'] != $uid) {
					$parentRecords[] = $row['uid'];
				} else {
					break;
				}
			}

			$parentRecords = array_reverse($parentRecords);
			foreach ($parentRecords AS $recordID) {
				$pointer = $this->tv_api->flexform_getPointersByRecord($recordID, $pid);
				if (!empty($pointer)) {
					$ret['targetCheckUid'] = $recordID;
					$ret['position'] = $pointer['0']['position'];
					break;
				}
			}
		}

		return $ret;
	} // end of 'function getTvPosition($pid, $uid) {..}'



	/**
	 * Imports the database
	 * 
	 * @param	array		$struct: converted xml structure
	 * @param	integer		$pid
	 * @return	void
	 */
	function importDatabase($struct, $pid){
		$this->pages = array();
		$i = 1;

		foreach ($struct AS $structElement) {
			//create page
			$this->createPage($structElement, $this->pages[$i], $pid);
			$i++;
		}
	} // end if "importDatabase() {...}"



	/**
	 * includes libraries
	 * 
	 * @param	array		$libraries
	 * @return	void
	 */
	function includeLibraries($libraries){
		foreach ($libraries AS $library) {
			require_once(PATH_site . $library['includeFile']);
			eval ('$this->'.$library['class'].' = t3lib_div::makeInstance('.$library['class'].');');
	
			//build argument list	
			$args = '';
			if (isset($library['args'])) {
				foreach ($library['args'] AS $key => $val) {
					$args .= ',$library[\'args\'][\''.$key.'\']';
				}
			}
			
			eval ('$this->'.$library['class'].'->init($this'.$args.');');

			$this->userClasses[] = $library['class'];
		}
	} // end of "includeLibraries() {...}"

	/**
	 * Logs performed action to the logfile
	 * 
	 * @param	string		$action
	 * @param	string		$table
	 * @param	integer		$affected_rows: rows affected by statement
	 * @param	array		$additionalInfo
	 * @return	void
	 */
	 function logAction($action, $table, $affected_rows, $additonalInfo = array()) {
	 	if ($this->logfile) {
	 		if ($action == 'deleteOldPages') {
	 			$msg = sprintf('Delete old pages (%s rows): %s',
	 				$affected_rows,
	 				print_r($additonalInfo, true)
	 			);
	 		} else {
		 		$lastquery = $this->typo_db->debug_lastBuiltQuery;
				$lastquery = str_replace(chr(9), ' ', $lastquery);
				$lastquery = str_replace(chr(10), ' ', $lastquery);
				$lastquery = str_replace(chr(13), ' ', $lastquery);
	 		
		 		$msg = sprintf('%s-Query on "%s" (%d rows): %s%s',
		 			$action,
		 			$table,
		 			$affected_rows,
		 			$lastquery,
		 			chr(10)
		 		);
	 		}
	 		gzwrite ($this->logfile, $msg);
	 	}
	 }

	/**
	 * writes the summary to the logfile
	 * 
	 * @param	array	$data: data for current entry
	 * @return	void
	 */
	 function logSummary($data) {
	 	global $LANG;
	 	
	 	if ($fp = fopen($this->pathUploadFolder . '/tx_mldbsync.log', 'ab')) {
	 		if ($data['importMode'] == 0) {
	 			$importMode = $LANG->getLL('importPagesAsVisible');
	 		} else { $importMode = $LANG->getLL('importPagesAsHidden'); }
	 		
	 		$msg = sprintf(
				'#####  dbsync import: %2$s - %3$s ####' . '%1$s' .
	 			'- %4$s: %5$s' . '%1$s' .
	 			'- %6$s: %7$s' . '%1$s' .
				'- %8$s: %9$s' . '%1$s' .
				'- %10$s: %11$s' . '%1$s' . 
				'- %12$s: %13$s' . '%1$s' . 
				'- %14$s: %15$s' . '%1$s' . 
				'- %16$s: %17$s' . '%1$s' . 
				'- %18$s: %19$s' . '%1$s' . 
				'- %20$s: %21$s' . '%1$s' . 
				'- %22$s: %23$s' . '%1$s' . 
				'- %24$s: %25$s' . '%1$s' . 
				'- %26$s: %27$s' . '%1$s%1$s',
	 			
	 			chr(10),
	 			date('Y-m-d H:i:s', $data['starttime']),
	 			date('Y-m-d H:i:s'),
	 			
	 			$LANG->getLL('xmlFile'),
	 			$data['filename'],
	 			
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
	 		
	 		fwrite ($fp, $msg);
	 		fclose ($fp);	
	 	}
	 	
	 } // end of "logSummary() {...}"



	/**
	 * Locks/Unlocks the typo3 users
	 * 
	 * @param	boolean		$lockUser
	 * @return	void
	 */
	function lockUsers($lockUsers)	{
		if ($lockUsers) {
			$this->exec_UPDATEquery(
				'be_users',
				sprintf('uid<>%s AND disable=%s',
					$this->typo_db->fullQuoteStr($this->userID, 'be_users'),
					$this->typo_db->fullQuoteStr(0, 'be_users')
				),
				array('disable' => 2),
				false
			);
		} else {
			$this->exec_UPDATEquery(
				'be_users',
				'disable='.$this->typo_db->fullQuoteStr(2, 'be_users'),
				array('disable' => 0),
				false
			);
		}
		
	} // end of "lockUsers() {...}"	



	/**
	 * parses the where clause and substitutes fields
	 * 
	 * @param	string		$clause: where clause
	 * @return	string		clause with substituted fields
	 */
	function parseString($clause){

		while (preg_match('/##([^#]*)##/', $clause, $reg) != false) {
			$val = eval('return $this->'.$reg[1].';');
			
			//currently addslashes is used instead of quoteStr or fullQuoteStr, 
			//because the tablename is unknown and furthermore there won't be a definition for external databases
			$clause = str_replace('##'.$reg[1].'##', addslashes($val), $clause);
		}

		return($clause);	
	} // end of "parseString() {...}"



	/**
	 * Starts the synchronisation according to the xml structure
	 * 
	 * @param	boolean		$manuallyExecuted: false if called from gabriel
	 * @return	void
	 */
	function startDatabaseImport($xmlFiles){
		global $LANG;

		//ensure that the import will not be chancelled by the user or by a lack of execution time
		ignore_user_abort(1);
		set_time_limit(0);

		//create lockfile
		touch($this->lockfile);

		//lock backend users if locking is demanded
		if ($this->cfg['lockUsers']) { 
			$this->lockUsers(true); 
		}
		
		//delete old hyperlink images
		$this->deleteHyperlinkImages();

		//go through all given xmlFiles and perform import
		foreach ($xmlFiles AS $xmlFile) {

			//check if values are complete
			if ($xmlFile['file'] == '' || $xmlFile['pid'] == '') {
				continue;
			}
			
			//save/update file in database
			if (isset($xmlFile['fileid'])) {
				$id = $xmlFile['fileid'];
				unset($xmlFile['fileid']);
				if (!isset($xmlFile['active'])) { $xmlFile['active'] = 0; }
				$this->exec_UPDATEquery(
					'tx_mldbsync_xmlfiles',
					'fileid='.$this->typo_db->fullQuoteStr($id, 'tx_mldbsync_xmlfiles'),
					$xmlFile,
					false
				);
			} else {
				if (!isset($xmlFile['active'])) { $xmlFile['active'] = 0; }
				$this->exec_INSERTquery('tx_mldbsync_xmlfiles', $xmlFile, false);
			}
			
			//check if file is activated
			if ($xmlFile['active'] == 0) {
				continue;
			}

			//prepare summary
			$this->summary[basename($xmlFile['file'])] = array(
				'xmlFile' => $LANG->getLL('invalidFile'),
				'filename' => basename($xmlFile['file']),
				'pid' => $xmlFile['pid'],
				'starttime' => time(),
				'database' => $LANG->getLL('notConnectedToDB'),
				'importMode' => $xmlFile['hidden'],
				
				'createdPages' => 0,
				'updatedPages' => 0,
				'createdContents' => 0,
				'updatedContents' => 0,
				
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
			} else {
				$struct = '';
				$currentEntry['xmlFile'] = $LANG->getLL('fileNotFound');
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

						//open logfile
						if ($this->cfg['enableLogging']) {
							$logfile = trim(basename($xmlFile['file'])) . strftime('_%Y%m%d%H%M%S') . '.log.gz';
							$this->logfile = gzopen($this->pathUploadFolder . '/'. $logfile, 'w9');
						}
						
						//include given libraries if given
						if (isset($struct['libraries'])) {
							$this->includeLibraries($struct['libraries']);	
						}

						//import Database
						$this->importDatabase($struct['pages'], $this->startPID);
						
						//delete pages that aren't in database any more
			 			$this->deleteOldPages();
			 			
						//close logfile
						if ($this->logfile) {
							gzclose($this->logfile);
							$this->logfile = false;
						}
					} 

				}

			} elseif ($struct != '') {
				 $currentEntry['xmlFile'] .= ': ' . $struct;
			}
		 			
		 	//log import summary for current xml
		 	$this->logSummary($currentEntry);
		}
		
		//delete caches
		$this->tce->clear_cacheCmd('all');
		
		//unlock backend users if locking is demanded
		if ($this->cfg['lockUsers']) { 
			$this->lockUsers(false); 
		}
		
		//remove lockfile
		t3lib_div::unlink_tempfile($this->lockfile);
		
	} // end of "startDatabaseImport() {...}"



	/**
	 * starts a user defined function
	 * 
	 * @param	array		$func: array with function definitions
	 * @param	integer		$pid: current pid
	 * @return	void
	 */
	function startUserfunc(&$func, $pid){
		$args = $pid;
		if (isset($func['args'])) {
			foreach ($func['args'] AS $arg) {
				if (strpos($arg, 'pages') !== false) {
					$args .= ',' . eval('return $this->'.$arg.';');
				} else $args .= ',' . $arg;
			}
		}
		eval ('$this->'.$func['func'].'('.$args.');');
	} // end of "startUserfunc() {...}"



} // end of 'class tx_mldbsync {....}'

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ml_dbsync/class.tx_mldbsync.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ml_dbsync/class.tx_mldbsync.php']);
}

?>
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
 * User extension of class localRecordList for the 'ml_dbsync'-extension 
 *
 * @author	Markus Friedrich <markus.friedrich@media-lights.de>
 */
class ux_localRecordList extends localRecordList {

	/**
	 * Writes the top of the full listing
	 *
	 * @param	array		Current page record
	 * @return	void		(Adds content to internal variable, $this->HTMLcode)
	 */
	function writeTop($row)	{
		global $LANG;

			// Makes the code for the pageicon in the top
		$this->pageRow = $row;
		$this->counter++;
		$alttext = t3lib_BEfunc::getRecordIconAltText($row,'pages');
		$iconImg = $this->getIconImage('pages',$row,$this->backPath,'class="absmiddle" title="'.htmlspecialchars($alttext).'"');
		$titleCol = 'test';	// pseudo title column name
		$this->fieldArray = Array($titleCol,'up');		// Setting the fields to display in the list (this is of course "pseudo fields" since this is the top!)


			// Filling in the pseudo data array:
		$theData = Array();
		$theData[$titleCol] = $this->widthGif;

			// Get users permissions for this row:
		$localCalcPerms = $GLOBALS['BE_USER']->calcPerms($row);

		$theData['up']=array();

			// Initialize control panel for currect page ($this->id):
			// Some of the controls are added only if $this->id is set - since they make sense only on a real page, not root level.
		$theCtrlPanel =array();

			// "View page" icon is added:
		$theCtrlPanel[]='<a href="#" onclick="'.htmlspecialchars(t3lib_BEfunc::viewOnClick($this->id,'',t3lib_BEfunc::BEgetRootLine($this->id))).'">'.
						'<img'.t3lib_iconWorks::skinImg($this->backPath,'gfx/zoom.gif','width="12" height="12"').' title="'.$LANG->sL('LLL:EXT:lang/locallang_core.php:labels.showPage',1).'" alt="" />'.
						'</a>';

			// If edit permissions are set (see class.t3lib_userauthgroup.php)
		if ($localCalcPerms&2)	{

				// Adding "Edit page" icon:
			if ($this->id)	{
				$params='&edit[pages]['.$row['uid'].']=edit';
				$theCtrlPanel[]='<a href="#" onclick="'.htmlspecialchars(t3lib_BEfunc::editOnClick($params,'',-1)).'">'.
								'<img'.t3lib_iconWorks::skinImg($this->backPath,'gfx/edit2.gif','width="11" height="12"').' title="'.$LANG->getLL('editPage',1).'" alt="" />'.
								'</a>';
			}

				// Adding "New record" icon:
			if (!$GLOBALS['SOBE']->modTSconfig['properties']['noCreateRecordsLink']) 	{
				$theCtrlPanel[]='<a href="#" onclick="'.htmlspecialchars('return jumpExt(\'db_new.php?id='.$this->id.'\');').'">'.
								'<img'.t3lib_iconWorks::skinImg('','gfx/new_el.gif','width="11" height="12"').' title="'.$LANG->getLL('newRecordGeneral',1).'" alt="" />'.
								'</a>';
			}

				// Adding "Hide/Unhide" icon:
			if ($this->id)	{
				if ($row['hidden'])	{
					$params='&data[pages]['.$row['uid'].'][hidden]=0';
					$theCtrlPanel[]='<a href="#" onclick="'.htmlspecialchars('return jumpToUrl(\''.$GLOBALS['SOBE']->doc->issueCommand($params,-1).'\');').'">'.
									'<img'.t3lib_iconWorks::skinImg($this->backPath,'gfx/button_unhide.gif','width="11" height="10"').' title="'.$LANG->getLL('unHidePage',1).'" alt="" />'.
									'</a>';
				} else {
					$params='&data[pages]['.$row['uid'].'][hidden]=1';
					$theCtrlPanel[]='<a href="#" onclick="'.htmlspecialchars('return jumpToUrl(\''.$GLOBALS['SOBE']->doc->issueCommand($params,-1).'\');').'">'.
									'<img'.t3lib_iconWorks::skinImg($this->backPath,'gfx/button_hide.gif','width="11" height="10"').' title="'.$LANG->getLL('hidePage',1).'" alt="" />'.
									'</a>';
				}
			}

				// Adding "move page" button:
			if ($this->id)	{
				$theCtrlPanel[]='<a href="#" onclick="'.htmlspecialchars('return jumpExt(\'move_el.php?table=pages&uid='.$row['uid'].'\');').'">'.
								'<img'.t3lib_iconWorks::skinImg($this->backPath,'gfx/move_'.($table=='tt_content'?'record':'page').'.gif','width="11" height="12"').' title="'.$LANG->getLL('move_page',1).'" alt="" />'.
								'</a>';
			}
		}

			// "Paste into page" link:
		if (($localCalcPerms&8) || ($localCalcPerms&16))	{
			$elFromTable = $this->clipObj->elFromTable('');
			if (count($elFromTable))	{
				$theCtrlPanel[]='<a href="'.htmlspecialchars($this->clipObj->pasteUrl('',$this->id)).'" onclick="'.htmlspecialchars('return '.$this->clipObj->confirmMsg('pages',$this->pageRow,'into',$elFromTable)).'">'.
								'<img'.t3lib_iconWorks::skinImg('','gfx/clip_pasteafter.gif','width="12" height="12"').' title="'.$LANG->getLL('clip_paste',1).'" alt="" />'.
								'</a>';
			}
		}

			// Finally, compile all elements of the control panel into table cells:
		if (count($theCtrlPanel))	{
			$theData['up'][]='

				<!--
					Control panel for page
				-->
				<table border="0" cellpadding="0" cellspacing="0" class="bgColor4" id="typo3-dblist-ctrltop">
					<tr>
						<td>'.implode('</td>
						<td>',$theCtrlPanel).'</td>
					</tr>
				</table>';
		}


			// Add "clear-cache" link:
		$theData['up'][]='<a href="'.htmlspecialchars($this->listURL().'&clear_cache=1').'">'.
						'<img'.t3lib_iconWorks::skinImg($this->backPath,'gfx/clear_cache.gif','width="14" height="14"').' title="'.$LANG->sL('LLL:EXT:lang/locallang_core.php:labels.clear_cache',1).'" alt="" />'.
						'</a>';

			// Add "CSV" link, if a specific table is shown:
		if ($this->table)	{
			$theData['up'][]='<a href="'.htmlspecialchars($this->listURL().'&csv=1').'">'.
							'<img'.t3lib_iconWorks::skinImg($this->backPath,'gfx/csv.gif','width="27" height="14"').' title="'.$LANG->sL('LLL:EXT:lang/locallang_core.php:labels.csv',1).'" alt="" />'.
							'</a>';
		}

			// Add "Export" link, if a specific table is shown:
		if ($this->table && t3lib_extMgm::isLoaded('impexp'))	{
			$theData['up'][]='<a href="'.htmlspecialchars($this->backPath.t3lib_extMgm::extRelPath('impexp').'app/index.php?tx_impexp[action]=export&tx_impexp[list][]='.rawurlencode($this->table.':'.$this->id)).'">'.
							'<img'.t3lib_iconWorks::skinImg($this->backPath,t3lib_extMgm::extRelPath('impexp').'export.gif',' width="18" height="16"').' title="'.$LANG->sL('LLL:EXT:lang/locallang_core.php:rm.export',1).'" alt="" />'.
							'</a>';
		}

			// Add "refresh" link:
		$theData['up'][]='<a href="'.htmlspecialchars($this->listURL()).'">'.
						'<img'.t3lib_iconWorks::skinImg($this->backPath,'gfx/refresh_n.gif','width="14" height="14"').' title="'.$LANG->sL('LLL:EXT:lang/locallang_core.php:labels.reload',1).'" alt="" />'.
						'</a>';


			// Add icon with clickmenu, etc:
		if ($this->id)	{	// If there IS a real page...:

				// Setting title of page + the "Go up" link:
			$theData[$titleCol].='<br /><span title="'.htmlspecialchars($row['_thePathFull']).'">'.htmlspecialchars(t3lib_div::fixed_lgd_cs($row['_thePath'],-$this->fixedL)).'</span>';
			$theData['up'][]='<a href="'.htmlspecialchars($this->listURL($row['pid'])).'">'.
							'<img'.t3lib_iconWorks::skinImg($this->backPath,'gfx/i/pages_up.gif','width="18" height="16"').' title="'.$LANG->sL('LLL:EXT:lang/locallang_core.php:labels.upOneLevel',1).'" alt="" />'.
							'</a>';

				// Make Icon:
			$theIcon = $this->clickMenuEnabled ? $GLOBALS['SOBE']->doc->wrapClickMenuOnIcon($iconImg,'pages',$this->id) : $iconImg;
		} else {	// On root-level of page tree:

				// Setting title of root (sitename):
			$theData[$titleCol].='<br />'.htmlspecialchars(t3lib_div::fixed_lgd_cs($GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'],-$this->fixedL));

				// Make Icon:
			$theIcon = '<img'.t3lib_iconWorks::skinImg($this->backPath,'gfx/i/_icon_website.gif','width="18" height="16"').' alt="" />';
		}

			// If there is a returnUrl given, add a back-link:
		if ($this->returnUrl)	{
			$theData['up'][]='<a href="'.htmlspecialchars(t3lib_div::linkThisUrl($this->returnUrl,array('id'=>$this->id))).'" class="typo3-goBack">'.
							'<img'.t3lib_iconWorks::skinImg($GLOBALS["BACK_PATH"],'gfx/goback.gif','width="14" height="14"').' title="'.$LANG->sL('LLL:EXT:lang/locallang_core.php:labels.goBack',1).'" alt="" />'.
							'</a>';
		}

			// Finally, the "up" pseudo field is compiled into a table - has been accumulated in an array:
		$theData['up']='
			<table border="0" cellpadding="0" cellspacing="0">
				<tr>
					<td>'.implode('</td>
					<td>',$theData['up']).'</td>
				</tr>
			</table>';

			// ... and the element row is created:
		$out.=$this->addelement(1,$theIcon,$theData,'',$this->leftMargin);

			// ... and wrapped into a table and added to the internal ->HTMLcode variable:
		$this->HTMLcode.='


		<!--
			Page header for db_list:
		-->
			<table border="0" cellpadding="0" cellspacing="0" id="typo3-dblist-top">
				'.$out.'
			</table>';
	}


	/**
	 * Rendering a single row for the list
	 *
	 * @param	string		Table name
	 * @param	array		Current record
	 * @param	integer		Counter, counting for each time an element is rendered (used for alternating colors)
	 * @param	string		Table field (column) where header value is found
	 * @param	string		Table field (column) where (possible) thumbnails can be found
	 * @param	integer		Indent from left.
	 * @return	string		Table row for the element
	 * @access private
	 * @see getTable()
	 */
	function renderListRow($table,$row,$cc,$titleCol,$thumbsCol,$indent=0)	{
		$iOut = '';

			// Background color, if any:
		$row_bgColor=
			$this->alternateBgColors ?
			(($cc%2)?'' :' bgcolor="'.t3lib_div::modifyHTMLColor($GLOBALS['SOBE']->doc->bgColor4,+10,+10,+10).'"') :
			'';

			// Initialization
		$alttext = t3lib_BEfunc::getRecordIconAltText($row,$table);
		$recTitle = t3lib_BEfunc::getRecordTitle($table,$row);

			// Incr. counter.
		$this->counter++;

			// The icon with link
		$iconImg = $this->getIconImage($table,$row,$this->backPath,'title="'.htmlspecialchars($alttext).'"'.($indent ? ' style="margin-left: '.$indent.'px;"' : ''));
		$theIcon = $this->clickMenuEnabled ? $GLOBALS['SOBE']->doc->wrapClickMenuOnIcon($iconImg,$table,$row['uid']) : $iconImg;

			// Preparing and getting the data-array
		$theData = Array();
		foreach($this->fieldArray as $fCol)	{
			if ($fCol==$titleCol)	{
				if ($GLOBALS['TCA'][$table]['ctrl']['label_alt'] && ($GLOBALS['TCA'][$table]['ctrl']['label_alt_force'] || !strcmp($row[$fCol],'')))	{
					$altFields=t3lib_div::trimExplode(',',$GLOBALS['TCA'][$table]['ctrl']['label_alt'],1);
					$tA=array();
					if ($row[$fCol])	{ $tA[]=$row[$fCol]; }
					while(list(,$fN)=each($altFields))	{
						$t = t3lib_BEfunc::getProcessedValueExtra($table,$fN,$row[$fN],$GLOBALS['BE_USER']->uc['titleLen'],$row['uid']);
						if($t)	{ $tA[] = $t; }
					}
					if ($GLOBALS['TCA'][$table]['ctrl']['label_alt_force'])	{ $t=implode(', ',$tA); }
					if ($t)	{ $recTitle = $t; }
				} else {
					$recTitle = t3lib_BEfunc::getProcessedValueExtra($table,$fCol,$row[$fCol],$GLOBALS['BE_USER']->uc['titleLen'],$row['uid']);
				}
				$theData[$fCol] = $this->linkWrapItems($table,$row['uid'],$recTitle,$row);
			} elseif ($fCol=='pid') {
				$theData[$fCol]=$row[$fCol];
			} elseif ($fCol=='_PATH_') {
				$theData[$fCol]=$this->recPath($row['pid']);
			} elseif ($fCol=='_CONTROL_') {
				$theData[$fCol]=$this->makeControl($table,$row);
			} elseif ($fCol=='_CLIPBOARD_') {
				$theData[$fCol]=$this->makeClip($table,$row);
			} elseif ($fCol=='_LOCALIZATION_') {
				list($lC1, $lC2) = $this->makeLocalizationPanel($table,$row);
				$theData[$fCol] = $lC1;
				$theData[$fCol.'b'] = $lC2;
			} elseif ($fCol=='_LOCALIZATION_b') {
				// Do nothing, has been done above.
			} else {
				$theData[$fCol]=htmlspecialchars(t3lib_BEfunc::getProcessedValueExtra($table,$fCol,$row[$fCol],100,$row['uid']));
			}
		}

			// Add row to CSV list:
		if ($this->csvOutput) $this->addToCSV($row);

			// Create element in table cells:
		$iOut.=$this->addelement(1,$theIcon,$theData,$row_bgColor);

			// Render thumbsnails if a thumbnail column exists and there is content in it:
		if ($this->thumbs && trim($row[$thumbsCol]))	{
			$iOut.=$this->addelement(4,'', Array($titleCol=>$this->thumbCode($row,$table,$thumbsCol)),$row_bgColor);
		}

			// Finally, return table row element:
		return $iOut;
	}


	/**
	 * Returns an icon image tag, 18x16 pixels, based on input information.
	 * This function is recommended to use in your backend modules.
	 * Usage: 60
	 *
	 * @param	string		The table name
	 * @param	array		The table row ("enablefields" are at least needed for correct icon display and for pages records some more fields in addition!)
	 * @param	string		The backpath to the main TYPO3 directory (relative path back to PATH_typo3)
	 * @param	string		Additional attributes for the image tag
	 * @param	boolean		If set, the icon will be grayed/shaded
	 * @return	string		<img>-tag
	 * @see getIcon()
	 */
	function getIconImage($table,$row=array(),$backPath,$params='',$shaded=FALSE)	{
		$iconFile = $this->getIcons($table,$row,$shaded);
		$str='<img'.t3lib_iconWorks::skinImg($backPath,$iconFile,'width="18" height="16"').(trim($params)?' '.trim($params):'');
		if (!stristr($str,'alt="'))	$str.=' alt=""';
		$str.=' />';
		return $str;
	}


	/**
	 * Creates the icon for input table/row
	 * Returns filename for the image icon, relative to PATH_typo3
	 * Usage: 24
	 *
	 * @param	string		The table name
	 * @param	array		The table row ("enablefields" are at least needed for correct icon display and for pages records some more fields in addition!)
	 * @param	boolean		If set, the icon will be grayed/shaded
	 * @return	string		Icon filename
	 * @see getIconImage()
	 */
	function getIcons($table,$row=array(),$shaded=FALSE)	{
		global $TCA, $PAGES_TYPES, $ICON_TYPES;

			// Flags:
		$doNotGenerateIcon = $GLOBALS['TYPO3_CONF_VARS']['GFX']['noIconProc'];				// If set, the icon will NOT be generated with GDlib. Rather the icon will be looked for as [iconfilename]_X.[extension]
		$doNotRenderUserGroupNumber = TRUE;		// If set, then the usergroup number will NOT be printed unto the icon. NOTICE. the icon is generated only if a default icon for groups is not found... So effectively this is ineffective...

			// First, find the icon file name. This can depend on configuration in TCA, field values and more:
		if ($table=='pages')	{
			if (!$iconfile = $PAGES_TYPES[$row['doktype']]['icon'])	{
				//check if current page is generated via db import and display special icon if it is
				$modeQuery = $GLOBALS['TYPO3_DB']->exec_SELECTquery('tx_mldbsync_created', 'pages', 'uid="'.$row['uid'].'"');
				$mode = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($modeQuery);
				if ($mode['tx_mldbsync_created'] == 1) {
					$iconfile = t3lib_extMgm::extRelPath('ml_dbsync') . 'pageIcon.gif';
				}
				else {
					$iconfile = $PAGES_TYPES['default']['icon'];
				}
			}
			if ($row['module'] && $ICON_TYPES[$row['module']]['icon'])	{
				$iconfile = $ICON_TYPES[$row['module']]['icon'];
			}
		} else {
			if (!$iconfile = $TCA[$table]['ctrl']['typeicons'][$row[$TCA[$table]['ctrl']['typeicon_column']]])	{
				$iconfile = (($TCA[$table]['ctrl']['iconfile']) ? $TCA[$table]['ctrl']['iconfile'] : $table.'.gif');
			}
		}

			// Setting path of iconfile if not already set. Default is "gfx/i/"
		if (!strstr($iconfile,'/'))	{
			$iconfile = 'gfx/i/'.$iconfile;
		}

			// Setting the absolute path where the icon should be found as a file:
		if (substr($iconfile,0,3)=='../')	{
			$absfile=PATH_site.substr($iconfile,3);
		} else {
			$absfile=PATH_typo3.$iconfile;
		}

			// Initializing variables, all booleans except otherwise stated:
		$hidden = FALSE;
		$timing = FALSE;
		$futuretiming = FALSE;
		$user = FALSE;				// In fact an integer value...
		$deleted = FALSE;
		$protectSection = FALSE;	// Set, if a page-record (only pages!) has the extend-to-subpages flag set.
		$noIconFound = $row['_NO_ICON_FOUND'] ? TRUE : FALSE;
		// + $shaded which is also boolean!

			// Icon state based on "enableFields":
		if (is_array($TCA[$table]['ctrl']['enablecolumns']))	{
			$enCols = $TCA[$table]['ctrl']['enablecolumns'];
				// If "hidden" is enabled:
			if ($enCols['disabled'])	{ if ($row[$enCols['disabled']]) { $hidden = TRUE; }}
				// If a "starttime" is set and higher than current time:
			if ($enCols['starttime'])	{ if (time() < intval($row[$enCols['starttime']]))	{ $timing = TRUE; }}
				// If an "endtime" is set:
			if ($enCols['endtime'])	{
				if (intval($row[$enCols['endtime']]) > 0)	{
					if (intval($row[$enCols['endtime']]) < time())	{
						$timing = TRUE;	// End-timing applies at this point.
					} else {
						$futuretiming = TRUE;		// End-timing WILL apply in the future for this element.
					}
				}
			}
				// If a user-group field is set:
			if ($enCols['fe_group'])	{
				$user = $row[$enCols['fe_group']];
				if ($user && $doNotRenderUserGroupNumber)	$user=100;	// Limit for user number rendering!
			}
		}

			// If "deleted" flag is set (only when listing records which are also deleted!)
		if ($col=$row[$TCA[$table]['ctrl']['delete']])	{
			$deleted = TRUE;
		}
			// Detecting extendToSubpages (for pages only)
		if ($table=='pages' && $row['extendToSubpages'] && ($hidden || $timing || $futuretiming || $user))	{
			$protectSection = TRUE;
		}

			// If ANY of the booleans are set it means we have to alter the icon:
		if ($hidden || $timing || $futuretiming || $user || $deleted || $shaded || $noIconFound)	{
			$flags='';
			$string='';
			if ($deleted)	{
				$string='deleted';
				$flags='d';
			} elseif ($noIconFound) {	// This is ONLY for creating icons with "?" on easily...
				$string='no_icon_found';
				$flags='x';
			} else {
				if ($hidden) $string.='hidden';
				if ($timing) $string.='timing';
				if (!$string && $futuretiming) {
					$string='futuretiming';
				}

				$flags.=
					($hidden ? 'h' : '').
					($timing ? 't' : '').
					($futuretiming ? 'f' : '').
					($user ? 'u' : '').
					($protectSection ? 'p' : '').
					($shaded ? 's' : '');
			}


				// Create tagged icon file name:
			$iconFileName_stateTagged = ereg_replace('.([[:alnum:]]+)$','__'.$flags.'.\1',basename($iconfile));

				// Check if tagged icon file name exists (a tagget icon means the icon base name with the flags added between body and extension of the filename, prefixed with underscore)
			if (@is_file(dirname($absfile).'/'.$iconFileName_stateTagged))	{	// Look for [iconname]_xxxx.[ext]
				return dirname($iconfile).'/'.$iconFileName_stateTagged;
			} elseif ($doNotGenerateIcon)	{		// If no icon generation can be done, try to look for the _X icon:
				$iconFileName_X = ereg_replace('.([[:alnum:]]+)$','__x.\1',basename($iconfile));
				if (@is_file(dirname($absfile).'/'.$iconFileName_X))	{
					return dirname($iconfile).'/'.$iconFileName_X;
				} else {
					return 'gfx/i/no_icon_found.gif';
				}
			} else {	// Otherwise, create the icon:
				$theRes= t3lib_iconWorks::makeIcon($GLOBALS['BACK_PATH'].$iconfile, $string, $user, $protectSection, $absfile, $iconFileName_stateTagged);
				return $theRes;
			}
		} else {
			return $iconfile;
		}
	}

}

if (defined("TYPO3_MODE") && $TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/ml_dbsync/class.ux_localrecordlist.php"])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/ml_dbsync/class.ux_localrecordlist.php"]);
}

?>

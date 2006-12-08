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
 * User extension of class rtePageTree for the 'ml_dbsync'-extension 
 *
 * @author	Markus Friedrich <markus.friedrich@media-lights.de>
 */

class ux_rtePageTree extends rtePageTree {

	/**
	 * Get icon for the row.
	 *
	 * @param	array		Item row.
	 * @return	string		Image tag.
	 */
	function getIcon($row) {
		if ($this->iconPath && $this->iconName) {
			$icon = '<img'.t3lib_iconWorks::skinImg('',$this->iconPath.$this->iconName,'width="18" height="16"').' alt="" />';
		} else {
			$icon = $this->getIconImage($this->table,$row,$this->backPath,'align="top" class="c-recIcon"');
		}

		return $this->wrapIcon($icon,$row);
	}

	/**
	 * Returns an icon image tag, 18x16 pixels, based on input information.
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

if (defined("TYPO3_MODE") && $TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/ml_dbsync/class.ux_rtepagetree.php"])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/ml_dbsync/class.ux_rtepagetree.php"]);
}
?>

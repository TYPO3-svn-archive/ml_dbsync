<?php

########################################################################
# Extension Manager/Repository config file for ext: "ml_dbsync"
#
# Auto generated 26-10-2007 14:42
#
# Manual updates:
# Only the data in the array - anything else is removed by next write.
# "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
	'title' => 'Database Sync',
	'description' => 'Creates typo3 page structure and contents from an external database',
	'category' => 'module',
	'author' => 'Markus Friedrich',
	'author_email' => 'markus.friedrich@media-lights.de',
	'shy' => '',
	'dependencies' => '',
	'conflicts' => '',
	'priority' => '',
	'module' => 'mod1',
	'state' => 'beta',
	'internal' => '',
	'uploadfolder' => 1,
	'createDirs' => '',
	'modify_tables' => 'pages,tt_content',
	'clearCacheOnLoad' => 0,
	'lockType' => '',
	'author_company' => 'medialights gmbh',
	'constraints' => array(
		'depends' => array(
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
	'version' => '0.10.2',
	'_md5_values_when_last_written' => 'a:28:{s:9:"ChangeLog";s:4:"9bd1";s:20:"class.ext_update.php";s:4:"2948";s:21:"class.tx_mldbsync.php";s:4:"e3a0";s:29:"class.tx_mldbsync_gabriel.php";s:4:"9c58";s:21:"class.ux_SC_index.php";s:4:"b296";s:26:"class.ux_localpagetree.php";s:4:"d646";s:28:"class.ux_localrecordlist.php";s:4:"9d20";s:24:"class.ux_rtepagetree.php";s:4:"536c";s:36:"class.ux_tx_rtehtmlarea_pagetree.php";s:4:"9959";s:24:"class.ux_webpagetree.php";s:4:"40e0";s:9:"error.gif";s:4:"0451";s:21:"ext_conf_template.txt";s:4:"fe84";s:12:"ext_icon.gif";s:4:"beda";s:17:"ext_localconf.php";s:4:"079f";s:14:"ext_tables.php";s:4:"0382";s:14:"ext_tables.sql";s:4:"9c3e";s:14:"link_popup.gif";s:4:"1ec5";s:12:"pageIcon.gif";s:4:"beda";s:15:"pageIcon__d.gif";s:4:"9e25";s:15:"pageIcon__h.gif";s:4:"9e25";s:16:"phpLibraries.inc";s:4:"ed74";s:14:"mod1/clear.gif";s:4:"cc11";s:13:"mod1/conf.php";s:4:"4d36";s:14:"mod1/index.php";s:4:"b079";s:18:"mod1/locallang.xml";s:4:"7ba8";s:22:"mod1/locallang_mod.xml";s:4:"475d";s:19:"mod1/moduleicon.gif";s:4:"beda";s:14:"doc/manual.sxw";s:4:"e626";}',
	'suggests' => array(
	),
);

?>
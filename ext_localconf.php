<?php

if (!defined ('TYPO3_MODE'))     die ('Access denied.');

//localpagetree
if (TYPO3_version < '4.1') {
	$TYPO3_CONF_VARS['BE']['XCLASS']['typo3/alt_db_navframe.php'] = t3lib_extMgm::extPath($_EXTKEY).'class.ux_localpagetree.php';
} else {
	$TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['typo3/class.webpagetree.php'] = t3lib_extMgm::extPath($_EXTKEY).'class.ux_webpagetree.php';
}

//rtepagetree - used from rte plugins
$TYPO3_CONF_VARS['BE']['XCLASS']['typo3/browse_links.php'] = t3lib_extMgm::extPath($_EXTKEY).'class.ux_rtepagetree.php';
$TYPO3_CONF_VARS['BE']['XCLASS']['ext/rtehtmlarea/rtehtmlarea_browse_links.php'] = t3lib_extMgm::extPath($_EXTKEY).'class.ux_rtepagetree.php';
$TYPO3_CONF_VARS['BE']['XCLASS']['ext/rtehtmlarea/mod3/class.tx_rtehtmlarea_browse_links.php'] = t3lib_extMgm::extPath($_EXTKEY).'class.ux_tx_rtehtmlarea_pagetree.php';

//localrecordlist
$TYPO3_CONF_VARS['BE']['XCLASS']['typo3/class.db_list_extra.inc'] = t3lib_extMgm::extPath($_EXTKEY).'class.ux_localrecordlist.php';

//sc_index
$TYPO3_CONF_VARS['BE']['XCLASS']['typo3/index.php'] = t3lib_extMgm::extPath($_EXTKEY).'class.ux_SC_index.php';

//register class for gabriel
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['gabriel']['include'][$_EXTKEY] = 'class.tx_mldbsync_gabriel.php';

?>

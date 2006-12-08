<?php

if (!defined ("TYPO3_MODE"))     die ("Access denied.");

//localpagetree
$TYPO3_CONF_VARS['BE']['XCLASS']['typo3/alt_db_navframe.php'] = t3lib_extMgm::extPath($_EXTKEY)."class.ux_localpagetree.php";

//rtepagetree - used from rte plugins
$TYPO3_CONF_VARS['BE']['XCLASS']['typo3/browse_links.php'] = t3lib_extMgm::extPath($_EXTKEY)."class.ux_rtepagetree.php";
$TYPO3_CONF_VARS['BE']['XCLASS']['ext/rtehtmlarea/rtehtmlarea_browse_links.php'] = t3lib_extMgm::extPath($_EXTKEY)."class.ux_rtepagetree.php";

//localrecordlist
$TYPO3_CONF_VARS['BE']['XCLASS']['typo3/class.db_list_extra.inc'] = t3lib_extMgm::extPath($_EXTKEY)."class.ux_localrecordlist.php";

//sc_index
$TYPO3_CONF_VARS['BE']['XCLASS']['typo3/index.php'] = t3lib_extMgm::extPath($_EXTKEY)."class.ux_SC_index.php";
?>
<?php 

class ux_exampleClass {
	var $typo_db;    //link to the typo3 database
	var $db;	 //link to the database that should be imported

	// the constructor function 
	function init(&$rCore) {
		$this->typo_db = $rCore->typo_db;
		$this->db = $rCore->db;
		$this->rCore = $rCore;
	}

}
	
?>

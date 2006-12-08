#
# Table structure for table 'tt_content'
#
CREATE TABLE tt_content (
	tx_mldbsync_created tinyint(3) default '0'
);

#
# Table structure for table 'pages'
#
CREATE TABLE pages (
	tx_mldbsync_created tinyint(3) DEFAULT '0'
);


#
# Table structure for table 'tx_mldbsync_log'
#
CREATE TABLE tx_mldbsync_log (
	logid int(11) NOT NULL auto_increment,
	path varchar(255) DEFAULT '',
	startPID int(11) DEFAULT '0',
	id int(11) DEFAULT '0',
	contentID varchar(255) DEFAULT '',
	
	PRIMARY KEY (logid),
);


#
# Table structure for table 'tx_mldbsync_log'
#
CREATE TABLE tx_mldbsync_xmlfiles (
	fileid int(11) NOT NULL auto_increment,
	active tinyint(3) DEFAULT '0',
	file varchar(255) DEFAULT '',
	pid int(11) DEFAULT '0',
	hidden tinyint(3) DEFAULT '0',
	
	PRIMARY KEY (fileid),
);

#
# Table structure for table 'sys_file'
#
CREATE TABLE sys_file (
    _migrateddamuid int(11) unsigned DEFAULT '0' NOT NULL
    KEY migratedRecords (_migrateddamuid)
);

#
# Table structure for table 'sys_category'
#
CREATE TABLE sys_category (
	_migrateddamcatuid int(11) unsigned DEFAULT '0' NOT NULL
);
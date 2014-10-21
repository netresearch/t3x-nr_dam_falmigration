#
# Table structure for table 'sys_file'
#
CREATE TABLE sys_file (
    _migrateddamuid int(11) unsigned DEFAULT '0' NOT NULL
    KEY migratedRecords (_migrateddamuid)
);

CREATE TABLE tx_dam (
    tx_nrdamfalmigration_storage INT(11) UNSIGNED DEFAULT '0' NOT NULL,
    tx_nrdamfalmigration_identifier_hash VARCHAR(40) DEFAULT '' NOT NULL
    KEY tx_nrdamfalmigration_sel01 (tx_nrdamfalmigration_storage, tx_nrdamfalmigration_identifier_hash),
    KEY tx_nrdamfalmigration_sel02 (deleted, tx_nrdamfalmigration_storage)
);

#
# Table structure for table 'sys_category'
#
CREATE TABLE sys_category (
	_migrateddamcatuid int(11) unsigned DEFAULT '0' NOT NULL
);
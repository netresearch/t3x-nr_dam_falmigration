<?php
declare(encoding = 'UTF-8');

/**
 * See class comment
 *
 * PHP version 5
 *
 * @category   Netresearch
 * @package    NR_DAM_FALMIGRATION
 * @subpackage Service
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch
 * @link       http://www.netresearch.de
 */

namespace Netresearch\NrDamFalmigration\Service;

/**
 * Service to migrate DAM files to FAL files including metadata and references
 *
 * @category   Netresearch
 * @package    NR_DAM_FALMIGRATION
 * @subpackage Service
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch
 * @link       http://www.netresearch.de
 */
class FileMigrationService extends AbstractMigrationService
{
    /**
     * The prefix of custom db fields
     * @var string
     */
    protected $prefix = 'tx_nrdamfalmigration_';
    
    /**
     * Prepare the schema and identifier fields (which are used to match sys_file
     * records with tx_dam records)
     * 
     * @return void
     */
    protected function init()
    {
        $this->checkIdentifierCollations();
        $this->fillIdentifierFields();
        $this->commitQueries();
    }
    
    /**
     * Check if the sys_file identifier_hash and our identifier hash have the same
     * collation and eventually adjust ours to the other one
     * 
     * @return void
     */
    protected function checkIdentifierCollations()
    {
        $fields = array(
            'sys_file' => $identifier = 'identifier_hash',
            'tx_dam' => $this->prefix . $identifier
        );
        foreach ($fields as $table => $field) {
            $res = $this->database->sql_query(
                "SHOW FULL COLUMNS FROM `$table` WHERE Field='$field'"
            );
            if ($res) {
                $row = $this->database->sql_fetch_assoc($res);
                $this->database->sql_free_result($res);
                $fields[$table] = $row;
            }
        }
        $dam = $fields['tx_dam'];
        $file = $fields['sys_file'];
        if ($dam['Collation'] != $file['Collation']) {
            $this->query(
                "ALTER TABLE tx_dam MODIFY COLUMN {$dam['Field']} {$dam['Type']} "
                . ($dam['Default'] !== null ? "DEFAULT '{$dam['Default']}' " : '')
                . ($dam['Null'] !== 'YES' ? 'NOT ' : '') . 'NULL '
                . "COLLATE '{$file['Collation']}';",
                "Making tx_dam.{$dam['Field']} COLLATE {$file['Collation']}"
            );
        }
    }
    
    /**
     * Set storage and identifier_hash on each tx_dam record to match against those
     * in later queries
     * 
     * @return void
     */
    protected function fillIdentifierFields()
    {
        foreach ($this->getStorages() as $storage) {
            $baseDir = $storage->getPublicUrl($storage->getRootLevelFolder());
            $baseDirLen = strlen($baseDir);
            $baseDirQuoted = $this->database->fullQuoteStr($baseDir, '');
            $this->query(
                "UPDATE tx_dam SET "
                . "{$this->prefix}storage = {$storage->getUid()}, "
                . "{$this->prefix}identifier_hash = SHA1(CONCAT("
                .    "SUBSTRING("
                .        "file_path, "
                .        "{$baseDirLen}, "
                .        "CHAR_LENGTH(tx_dam.file_path) - {$baseDirLen}"
                .        "), "
                . "'/', "
                . "file_name)) "
                . "WHERE SUBSTRING(file_path, 1, {$baseDirLen}) = {$baseDirQuoted};",
                'Creating identifiers'
            );
        }
    }

    /**
     * Find files that are already in sys_file AND in tx_dam but were not migrated by
     * this service. Override their metadata with that from DAM and connect them with
     * their DAM counterparts by setting _migrateddamuid
     * 
     * @return void
     */
    protected function migrateFromDamToExistingFalRecords()
    {
        $commonWhere
            = "tx_dam.{$this->prefix}storage = sys_file.storage AND "
            . "tx_dam.{$this->prefix}identifier_hash = sys_file.identifier_hash AND "
            . "tx_dam.deleted = 0 AND "
            . "sys_file._migrateddamuid = 0";
            
        $this->query(
            $this->createUpdateQuery(
                'sys_file_metadata',
                'sys_file, tx_dam',
                'sys_file_metadata.file = sys_file.uid AND ' . $commonWhere
            ),
            'Overriding existing metadata'
        );
        
        $this->query(
            'UPDATE sys_file, tx_dam SET sys_file._migrateddamuid = tx_dam.uid '
            . 'WHERE ' . $commonWhere . ';',
            'Connecting sys_file records to eventually existing dam counterparts'
        );
    }

    /**
     * Migrate all files by storage from DAM
     * 
     * @return void
     */
    protected function migrateFiles()
    {        
        foreach ($this->getStorages() as $storage) {
            $baseDir = $storage->getPublicUrl($storage->getRootLevelFolder());
            $baseDirLen = strlen($baseDir);
            $this->query(
                $this->createInsertQuery(
                    'sys_file',
                    'tx_dam LEFT JOIN sys_file sf2 ON '
                    . '(tx_dam.uid = sf2._migrateddamuid)',
                    "sf2.uid IS NULL AND tx_dam.deleted = 0 AND "
                    . "tx_dam.{$this->prefix}storage = :storageUid",
                    'tx_dam.uid ASC',
                    array(
                        'baseDir' => $baseDir,
                        'baseDirLen' => $baseDirLen,
                        'storageUid' => $storage->getUid()
                    )
                ),
                "Migrating not migrated files for storage '{$storage->getName()}' "
                . "({$storage->getUid()})"
            );
        }
    }
    
    /**
     * Migrate all the metadata
     * 
     * @return void
     */
    protected function migrateMetadata()
    {
        $this->query(
            $this->createInsertQuery(
                'sys_file_metadata',
                'sys_file '
                . 'INNER JOIN tx_dam ON (sys_file._migrateddamuid = tx_dam.uid) '
                . 'LEFT JOIN sys_file_metadata sfm ON (sys_file.uid = sfm.file)',
                'sfm.uid IS NULL',
                'sys_file.uid ASC'
            ),
            'Migrating not yet migrated metadata'
        );
    }
    
    /**
     * Insert relations from DAM to FAL
     * 
     * @return void
     */
    protected function migrateReferences()
    {
        $this->query(
            $this->createInsertQuery(
                'sys_file_reference',
                'tx_dam_mm_ref mm '
                . 'INNER JOIN sys_file sf ON (sf._migrateddamuid = mm.uid_local) '
                . 'LEFT JOIN sys_file_reference sfrm ON (sf.uid = sfrm.uid_local)',
                'sfrm.uid IS NULL'
            ),
            'Migrating references'
        );
    }
    
    /**
     * Set foreign fields of 'inline' relations to 1, as TYPO3 otherwise won't show
     * the relations in TCE forms
     * 
     * @return void
     */
    public function migrateRelatedRecords()
    {
        \t3lib_extMgm::loadBaseTca();
        
        $tablesAndFields = $this->database->exec_SELECTgetRows(
            'tablenames t, fieldname f',
            'sys_file_reference',
            '1',
            'tablenames, fieldname'
        );
        $tableFields = array();
        foreach ($tablesAndFields as $tableAndField) {
            if (!array_key_exists($tableAndField['t'], $tableFields)) {
                $tableFields[$tableAndField['t']] = array();
            }
            $tableFields[$tableAndField['t']][] = $tableAndField['f'];
        }
        $warnings = array();
        foreach ($tableFields as $table => $fields) {
            $fields = $this->getMigratableFields($table, $fields, $warnings);
            foreach ($fields as $field) {
                $this->query(
                    "UPDATE $table tt, sys_file_reference sfr "
                    . "SET tt.$field = 1 WHERE "
                    . "sfr.uid_foreign = tt.uid AND "
                    . "sfr.tablenames = '$table' AND "
                    . "sfr.fieldname = '$field';",
                    "Setting relations on local field $table.$field"
                );
            }
        }
        if ($warnings) {
            if ($this->dryrun) {
                $this->outputLine('/*');
            }
            foreach ($warnings as $warning) {
                $this->outputLine('[WARNING] ' . $warning);
            }
            if ($this->dryrun) {
                $this->outputLine('*/');
            }
        }
    }
    
    /**
     * Get the foreign fields that could be migrated
     * 
     * @param string $table    Table name
     * @param array  $fields   All fields
     * @param array  $warnings Array to be filled with warnings
     * 
     * @global type $TCA
     * 
     * @return array
     */
    protected function getMigratableFields($table, array $fields, array &$warnings)
    {
        global $TCA;
        
        $migratableFields = array();
        if (array_key_exists($table, $TCA)) {
            $dbFields = $this->database->admin_get_fields($table);
            foreach ($fields as $field) {
                if (array_key_exists($field, $TCA[$table]['columns'])) {
                    if (!$TCA[$table]['columns']['config']['type'] != 'inline') {
                        $warnings[] = "Referenced field not configured as "
                        . "'inline' field in \$TCA: $table.$field";
                        continue;
                    }
                } else {
                    $warnings[] = 'Referenced field not configured in $TCA: '
                        . "$table.$field";
                    continue;
                }
                if (!array_key_exists($field, $dbFields)) {
                    $warnings[] = "Local field doesn't exist:  $table.$field";
                    continue;
                }
                $migratableFields[] = $field;
            }
        } else {
            $warnings[] = "Referenced table doesn't exist in \$TCA:  $table";
        }
        return $migratableFields;
    }
}
?>

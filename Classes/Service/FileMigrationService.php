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

use TYPO3\CMS\Core\Resource\ResourceStorage;

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
     * Migrate all files by storage from DAM
     * 
     * @return void
     */
    protected function migrateFiles()
    {
        $this->query(
            'TRUNCATE TABLE sys_file;',
            'Removing existing FAL file records'
        );
        
        foreach ($this->getStorages() as $storage) {
            $baseDir = $storage->getPublicUrl($storage->getRootLevelFolder());
            $baseDirLen = strlen($baseDir);
            $this->query(
                $this->createInsertQuery(
                    'sys_file',
                    'tx_dam',
                    "tx_dam.deleted = 0 AND "
                    . "SUBSTRING(tx_dam.file_path, 1, :baseDirLen) = :baseDir",
                    'tx_dam.uid ASC',
                    array(
                        'baseDir' => $baseDir,
                        'baseDirLen' => $baseDirLen,
                        'storageUid' => $storage->getUid()
                    )
                ),
                "Migrating files for storage '{$storage->getName()}' "
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
            'TRUNCATE TABLE sys_file_metadata;',
            'Removing existing FAL metadata'
        );
        $this->query(
            $this->createInsertQuery(
                'sys_file_metadata',
                'sys_file, tx_dam',
                'sys_file._migrateddamuid = tx_dam.uid',
                'sys_file.uid ASC'
            ),
            'Migrating metadata'
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
            'TRUNCATE TABLE sys_file_reference;',
            'Removing existing FAL references'
        );
        $this->query(
            $this->createInsertQuery(
                'sys_file_reference',
                'tx_dam_mm_ref mm, sys_file sf',
                'sf._migrateddamuid = mm.uid_local'
            ),
            'Migrating references'
        );
    }
    
    /**
     * Set foreign fields of 'inline' relations to 1, as TYPO3 otherwise won't show
     * the relations in TCE forms
     * 
     * @global type $TCA
     * 
     * @return void
     */
    public function sanitizeRelatedRecords()
    {
        global $TCA;
        \t3lib_extMgm::loadBaseTca();
        
        $tablesAndFields = $this->database->exec_SELECTgetRows(
            'tablenames, fieldname',
            'sys_file_reference',
            '1',
            'tablenames, fieldname'
        );
        $tableFields = array();
        foreach ($tablesAndFields as $tableAndField) {
            if (!array_key_exists($tableAndField['tablenames'], $tableFields)) {
                $tableFields[$tableAndField['tablenames']] = array();
            }
            $tableFields[$tableAndField['tablenames']][] = $tableAndField['fieldname'];
        }
        $warnings = array();
        foreach ($tableFields as $table => $fields) {
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
                    if (array_key_exists($field, $dbFields)) {
                        $this->query(
                            "UPDATE $table tt, sys_file_reference sfr "
                            . "SET tt.$field = 1 WHERE "
                            . "sfr.uid_foreign = tt.uid AND "
                            . "sfr.tablenames = '$table' AND "
                            . "sfr.fieldname = '$field';",
                            "Setting relations on local field $table.$field"
                        );
                    } else {
                        $warnings[] = "Local field doesn't exist:  $table.$field";
                    }
                }
            } else {
                $warnings[] = "Referenced table doesn't exist in \$TCA:  $table";
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
}
?>

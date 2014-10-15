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
class FileMigrationService extends AbstractService
{
    /**
     * Run the migration
     * 
     * @return void
     */
    public function run()
    {
        $this->migrateFiles();
        $this->migrateMetadata();
        $this->migrateRelations();        
        
        if (!$this->dryrun) {
            $this->commitQueries();
        } else {
            $this->dumpQueries();
        }
    }

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
    protected function migrateRelations()
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
}
?>

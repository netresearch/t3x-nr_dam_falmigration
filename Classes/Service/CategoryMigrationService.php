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
class CategoryMigrationService extends AbstractMigrationService
{
    /**
     * Migrate all categories
     * 
     * @return void
     */
    protected function migrateCategories()
    {
        $this->query(
            "DELETE FROM sys_category WHERE _migrateddamcatuid != '';",
            'Removing already migrated category records'
        );
        $this->query(
            $this->createInsertQuery('sys_category', 'tx_dam_cat dc'),
            'Migrating categories'
        );
        $this->query(
            'UPDATE sys_category sc1, sys_category sc2, tx_dam_cat dc '
            . 'SET sc1.parent = sc2.uid WHERE '
            . 'sc1._migrateddamcatuid = dc.uid AND '
            . 'sc2._migrateddamcatuid = dc.parent_id AND '
            . 'dc.parent_id > 0;',
            'Migrating categories child to parent relations'
        );
    }
    
    /**
     * Migrate all category relations
     * 
     * @return void
     */
    protected function migrateCategoryToFileRelations()
    {
        $this->query(
            "DELETE FROM sys_category_record_mm WHERE tablenames = 'sys_file';",
            'Removing references from categories to files'
        );
        $this->query(
            $this->createInsertQuery(
                'sys_category_record_mm',
                'tx_dam_mm_cat dcm, sys_category sc, sys_file sf',
                'sc._migrateddamcatuid = dcm.uid_foreign AND '
                . 'sf._migrateddamuid = dcm.uid_local'
            ),
            'Migrating references from categories to files'
        );
    }
    
    /**
     * Set the categories field on the metadata table to 1 when file has categories
     * 
     * @return void
     */
    protected function sanitizeForeignRecords()
    {
        $this->query(
            "UPDATE sys_file_metadata sfm, sys_category_record_mm scrm "
            . "SET sfm.categories = 1 WHERE "
            . "scrm.uid_foreign = sfm.uid AND "
            . "scrm.tablenames = 'sys_file_metadata' AND "
            . "scrm.fieldname = 'categories';",
            'Setting categories fields on sys_file_metadata'
        );
    }
}
?>

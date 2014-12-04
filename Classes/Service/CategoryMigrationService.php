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
            $this->createInsertQuery(
                'sys_category',
                'tx_dam_cat dc LEFT JOIN sys_category sc2 '
                . 'ON (dc.uid = sc2._migrateddamcatuid)',
                'sc2.uid IS NULL'
            ),
            'Migrating categories'
        );
        $this->query(
            $this->createUpdateQuery(
                'sys_category sc1, sys_category sc2, tx_dam_cat dc',
                array('sc1.parent = sc2.uid'),
                'sc1._migrateddamcatuid = dc.uid AND '
                . 'sc2._migrateddamcatuid = dc.parent_id AND '
                . 'dc.parent_id > 0'
            ),
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
            $this->createInsertQuery(
                'sys_category_record_mm',
                'tx_dam_mm_cat dcm, sys_category sc, sys_file sf, '
                . 'sys_file_metadata sfm LEFT JOIN sys_category_record_mm scr2 ON ('
                . 'scr2.fieldname = \'categories\' AND '
                . 'scr2.tablenames = \'sys_file_metadata\' AND '
                . 'sfm.uid = scr2.uid_foreign)',
                'sc._migrateddamcatuid = dcm.uid_foreign AND '
                . 'sf._migrateddamuid = dcm.uid_local AND '
                . 'sfm.file = sf.uid AND '
                . 'scr2.uid_local IS NULL'
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
            $this->createUpdateQuery(
                'sys_file_metadata sfm, sys_category_record_mm scrm',
                array('sfm.categories' => 1),
                "sfm.categories != 1 AND "
                . "scrm.uid_foreign = sfm.uid AND "
                . "scrm.tablenames = 'sys_file_metadata' AND "
                . "scrm.fieldname = 'categories'"
            ),
            'Setting categories fields on sys_file_metadata'
        );
    }
}
?>

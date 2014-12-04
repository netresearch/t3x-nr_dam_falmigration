<?php
declare(encoding = 'UTF-8');

/**
 * See class comment
 *
 * PHP version 5
 *
 * @category   Netresearch
 * @package    NR_DAM_FALMIGRATION
 * @subpackage Updates
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch
 * @link       http://www.netresearch.de
 */

namespace Netresearch\NrDamFalmigration\Updates;

/**
 * Wizard to migrate DAM categories to sys_categories including references
 *
 * @category   Netresearch
 * @package    NR_DAM_FALMIGRATION
 * @subpackage Updates
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch
 * @link       http://www.netresearch.de
 */
class MigrateDamCategoriesUpdate extends AbstractDamMigrationUpdate
{
    /**
     * @var string
     */
    protected $title
        = 'Migrate categories and their references from tx_dam to sys_category';

    /**
     * @var string
     */
    protected $description
        = 'Migrates the records from tx_dam_cat and tx_dam_cat_mm to
        sys_category and sys_category_record_mm.<br/>
        This process is repeatable - if it fails or there are new tx_dam categories,
        you can simply run it again and the already imported records won\'t be
        touched.<br/>
        If the wizard takes to long to run in the Install Tool, you can also run it
        from command line:
        <pre>
        php typo3/cli_dispatch.phpsh extbase dammigration:migratecategories</pre>';

    /**
     * @var string
     */
    protected $serviceClass
        = 'Netresearch\\NrDamFalmigration\\Service\\CategoryMigrationService';
}
?>

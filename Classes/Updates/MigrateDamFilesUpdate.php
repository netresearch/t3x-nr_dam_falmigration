<?php

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
 * Wizard to migrate DAM files to FAL files including metadata and references
 *
 * @category   Netresearch
 * @package    NR_DAM_FALMIGRATION
 * @subpackage Updates
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch
 * @link       http://www.netresearch.de
 */
class MigrateDamFilesUpdate extends AbstractDamMigrationUpdate
{
    /**
     * @var string
     */
    protected $title
        = 'Migrate files, metadata and references from tx_dam to sys_file';

    /**
     * @var string
     */
    protected $description
        = 'Migrates the records from tx_dam and tx_dam_mm_ref to
        sys_file, sys_file_metadata and sys_file_reference (thus the files are not
        required to be physically available at this instance).<br/>
        This process is repeatable - if it fails or there are new tx_dam files, you
        can simply run it again and the already imported files won\'t be touched.
        <br/>
        If the wizard takes to long to run in the Install Tool, you can also run it
        from command line:
        <pre>php typo3/cli_dispatch.phpsh extbase dammigration:migratefiles</pre>';

    /**
     * @var string
     */
    protected $serviceClass
        = 'Netresearch\\NrDamFalmigration\\Service\\FileMigrationService';
}
?>

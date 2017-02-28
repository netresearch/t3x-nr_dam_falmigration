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
class MigratePermissionsUpdate extends AbstractDamMigrationUpdate
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
        = 'Migrates the permissions from be_users and be_groups from DAM to FAL.
        The permissions for dam will be replaced with the related setting 
        <br/>
        If the wizard takes to long to run in the Install Tool, you can also run it
        from command line:
        <pre>php typo3/cli_dispatch.phpsh extbase dammigration:migratefiles</pre>';

    /**
     * @var string
     */
    protected $serviceClass
        = 'Netresearch\\NrDamFalmigration\\Service\\PermissionMigrationService';
}
?>

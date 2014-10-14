<?php
declare(encoding = 'UTF-8');

/**
 * See class comment
 *
 * PHP version 5
 *
 * @category   Netresearch
 * @package    NR_DAM_FALMIGRATION
 * @subpackage Controller
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch
 * @link       http://www.netresearch.de
 */

namespace Netresearch\NrDamFalmigration\Controller;

use TYPO3\CMS\Extbase\Mvc\Controller\CommandController;

/**
 * Command to migrate from DAM to FAL
 *
 * @category   Netresearch
 * @package    NR_DAM_FALMIGRATION
 * @subpackage Controller
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch
 * @link       http://www.netresearch.de
 */
class DamMigrationCommandController extends CommandController
{
    /**
     * Run all required migration commands
     * 
     * @param integer $storage Limit import to a storage (which must have a Local
     *                         driver)
     * @param boolean $dryrun  Only show the mysql statements, which would be
     *                         executed
     * 
     * @return void
     */
    public function migrateFilesCommand($storage = null, $dryrun = false)
    {
        /*@var $service \Netresearch\NrDamFalmigration\Service\FileMigrationService*/
        $service = $this->objectManager->get(
            'Netresearch\\NrDamFalmigration\\Service\\FileMigrationService'
        );
        $service
            ->setResponse($this->response)
            ->setStorageUid($storage)
            ->setDryrun($dryrun)
            ->run();
    }
}
?>

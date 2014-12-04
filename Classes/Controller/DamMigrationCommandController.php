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

use Netresearch\NrDamFalmigration\Service\AbstractMigrationService;
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
     * Migrate tx_dam to sys_file and sys_file_metadata and 
     * tx_dam_mm_ref to sys_file_reference
     *
     * @param integer $storage Limit import to a storage
     *                         (which must have a Local driver)
     * @param boolean $dryrun  Only show the mysql statements,
     *                         which would be executed
     * @param boolean $count   Only count the records, expected to be migrated
     * 
     * @return void
     */
    public function migrateFilesCommand(
        $storage = null, $dryrun = false, $count = false
    ) {
        $service = $this->objectManager->get(
            'Netresearch\\NrDamFalmigration\\Service\\FileMigrationService'
        );
        $service->setStorageUid($storage);
        $this->runService($service, $dryrun, $count);
    }
    
    /**
     * Migrate categories from tx_dam_cat to sys_category and
     * their relations from tx_dam_mm_cat to sys_category_record_mm
     * 
     * @param boolean $dryrun Only show the mysql statements, which would be
     *                        executed
     * @param boolean $count  Only count the records, expected to be migrated
     * 
     * @return void
     */
    public function migrateCategoriesCommand($dryrun = false, $count = false)
    {
        $service = $this->objectManager->get(
            'Netresearch\\NrDamFalmigration\\Service\\CategoryMigrationService'
        );
        $this->runService($service, $dryrun, $count);
    }

    /**
     * Execute the service
     *
     * @param AbstractMigrationService $service The service to run
     * @param boolean                  $dryrun  Dryrun it?
     * @param boolean                  $count   Only count the records?
     *
     * @return void
     */
    protected function runService(AbstractMigrationService $service, $dryrun, $count)
    {
        $res = $service
            ->setDryrun($dryrun)
            ->setCount($count)
            ->run();

        if ($count) {
            foreach ($res as $title => $number) {
                $this->outputLine($title . ': ' . $number);
            }
        }
    }
}
?>

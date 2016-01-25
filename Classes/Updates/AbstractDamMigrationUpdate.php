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

use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Updates\AbstractUpdate;

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
abstract class AbstractDamMigrationUpdate extends AbstractUpdate
{
    /**
     * @var string The title of the wizard
     */
    protected $title;

    /**
     * @var string The description of the wizard
     */
    protected $description;

    /**
     * @var string The service class name to use
     */
    protected $serviceClass;

    /**
     * Get an instance of $this->serviceClass
     *
     * @return \Netresearch\NrDamFalmigration\Service\AbstractMigrationService
     */
    protected function getService()
    {
        $service = GeneralUtility::makeInstance($this->serviceClass);
        $service->setFlushOutputs(false);
        return $service;
    }

    /**
     * Checks whether updates are required.
     *
     * @param string $description The description for the update
     *
     * @return boolean Whether an update is required (TRUE) or not (FALSE)
     */
    public function checkForUpdate(&$description)
    {
        if ($this->isWizardDone()) {
            return false;
        }

        $service = $this->getService();
        $service->setCount(true);

        $description .= $this->description;

        try {
            $expectedRecords = $service->run();
        } catch (\Exception $e) {
            $description .= $this->renderMessage(
                'This wizard relies on the new sys_file and sys_category tables as
                well as the tx_dam tables (tx_dam, tx_dam_mm_ref, tx_dam_cat,
                tx_dam_mm_cat). <br/>
                Please make sure, you ran the Database Analyzer before. <br/>
                Error was: <br/> ' . $e->getMessage(),
                'Error while counting the expected records'
            );
            return true;
        }

        if (max($expectedRecords) > 0) {
            $i = 0;
            $description .= '<strong>Expected number of records to be migrated:'
                . '</strong>';
            foreach ($expectedRecords as $title => $count) {
                $description .= "<br/>{$title}: <strong>{$count}</strong>";
                if ($i > 0) {
                    $description .= ' (probably depending on previous migrations)';
                }
                $i++;
            }
        } else {
            return false;
        }

        return true;
    }

    /**
     * Render a flash message
     *
     * @param string      $message  Message body
     * @param string|null $title    Title of the message
     * @param int         $severity Severity
     *
     * @return string
     */
    protected function renderMessage(
        $message, $title = null, $severity = FlashMessage::ERROR
    ) {
        return GeneralUtility::makeInstance(
            'TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
            $message,
            $title,
            $severity
        )->render();
    }

    /**
     * Performs the accordant updates.
     *
     * @param array $dbQueries      Queries done in this update
     * @param mixed $customMessages Custom messages
     *
     * @return boolean Whether everything went smoothly or not
     */
    public function performUpdate(array &$dbQueries, &$customMessages)
    {
        $service = $this->getService();

        try {
            $dbQueries = $service->run();
        } catch (\Exception $e) {
            $customMessages .= $service->getResponse();
            $message = GeneralUtility::makeInstance(
                'TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
                (string) $e,
                'An error occured',
                \TYPO3\CMS\Core\Messaging\FlashMessage::ERROR
            );
            $customMessages .= $message->render();

            return false;
        }

        $customMessages .= $service->getResponse();

        $this->markWizardAsDone(1);

        return true;
    }
}
?>

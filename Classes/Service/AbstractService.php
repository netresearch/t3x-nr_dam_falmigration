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

use TYPO3\CMS\Extbase\Mvc\Cli\Response;

/**
 * Abstract service class with basic setters and out put methods
 *
 * @category   Netresearch
 * @package    NR_DAM_FALMIGRATION
 * @subpackage Service
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch
 * @link       http://www.netresearch.de
 */
abstract class AbstractService
{
    /**
     * @var Response
     */
    protected $response;
    
    /**
     * @var int|null
     */
    protected $storageUid;

    /**
     * @var \TYPO3\CMS\Core\Database\DatabaseConnection
     */
    protected $database;
    
    /**
     * @var \TYPO3\CMS\Core\Resource\StorageRepository
     * @inject
     */
    protected $storageRepository;
    
    /**
     * @var boolean
     */
    protected $dryrun = false;

    /**
     * Construct - set DB and call init
     */
    public function __construct()
    {
        $this->database = $GLOBALS['TYPO3_DB'];
        $this->init();
    }
    
    /**
     * Override if needed
     * 
     * @return void
     */
    protected function init()
    {
    }

    /**
     * Called at last by controller or sth.
     * 
     * @return void
     */
    abstract public function run();

    /**
     * Set the response (needed to output stuff)
     * 
     * @param \TYPO3\CMS\Extbase\Mvc\Cli\Response $response Response
     * 
     * @return $this
     */
    public function setResponse(Response $response)
    {
        $this->response = $response;
        return $this;
    }
    
    /**
     * Set the storage id to work on (null for all)
     * 
     * @param int|null $storageUid Storage id
     * 
     * @return $this
     */
    public function setStorageUid($storageUid)
    {
        $this->storageUid = is_numeric($storageUid) ? (int) $storageUid : null;
        return $this;
    }
    
    /**
     * Set wether to only execute in dry run mode
     * 
     * @param boolean $dryrun Dryrun
     * 
     * @return $this
     */
    public function setDryrun($dryrun)
    {
        $this->dryrun = $dryrun;
        return $this;
    }
    
    /**
     * Get the storages - either limited to storageUid or all Local ones
     * 
     * @return array<\TYPO3\CMS\Core\Resource\ResourceStorage>
     */
    protected function getStorages()
    {
        if ($this->storageUid !== null) {
            $storages = array(
                $this->storageRepository->findByUid($this->storageUid)
            );
        } else {
            $storages = $this->storageRepository->findByStorageType('Local');
        }
        foreach ($storages as $storage) {
            if ($storage->getDriverType() !== 'Local') {
                throw new Exception\IllegalDriverType();
            }
        }
        return $storages;
    }
    

    /**
     * Outputs specified text to the console window
     * You can specify arguments that will be passed to the text via sprintf
     * 
     * @param string $text      Text to output
     * @param array  $arguments Optional arguments to use for sprintf
     * @param bool   $flush     Whether to immediately flush the output
     *
     * @see http://www.php.net/sprintf
     * 
     * @return void
     */
    protected function output($text, array $arguments = array(), $flush = true)
    {
        if ($arguments !== array()) {
            $text = vsprintf($text, $arguments);
        }
        $this->response->appendContent($text);
        if ($flush) {
            $this->response->send();
        }
    }

    /**
     * Outputs specified text to the console window and appends a line break
     *
     * @param string $text      Text to output
     * @param array  $arguments Optional arguments to use for sprintf
     * @param bool   $flush     Whether to immediately flush the output
     * 
     * @return string
     */
    protected function outputLine(
        $text = '', array $arguments = array(), $flush = true
    ) {
        return $this->output($text . PHP_EOL, $arguments);
    }

    /**
     * Exits the CLI through the dispatcher
     * An exit status code can be specified @see http://www.php.net/exit
     *
     * @param integer $exitCode Exit code to return on exit
     * 
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
     * 
     * @return void
     */
    protected function quit($exitCode = 0)
    {
        $this->response->setExitCode($exitCode);
        throw new \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException();
    }

    /**
     * Sends the response and exits the CLI without any further code execution
     * Should be used for commands that flush code caches.
     *
     * @param integer $exitCode Exit code to return on exit
     * 
     * @return void
     */
    protected function sendAndExit($exitCode = 0)
    {
        $this->response->send();
        die($exitCode);
    }
}
?>

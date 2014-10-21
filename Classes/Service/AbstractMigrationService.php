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
abstract class AbstractMigrationService
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
     * SQL queries to commit later on
     * @var array
     */
    private $queries = array();

    /**
     * Mapping for each table with each having target columns as key and source
     * columns or statements as values
     * @var array
     */
    protected $mapping = array();
    
    /**
     * Possible overrides for mapping (use null values to remove mappings)
     * @var array
     */
    protected static $mappingOverrides = array();
    
    /**
     * Construct - set DB, apply mappings and call init
     */
    public function __construct()
    {
        $this->database = $GLOBALS['TYPO3_DB'];
        
        foreach (self::$mappingOverrides as $info) {
            list($table, $overrides) = $info;
            if (!array_key_exists($table, $this->mapping)) {
                $this->mapping[$table] = array();
            }
            foreach ($overrides as $toColumn => $fromColumn) {
                if ($fromColumn === null) {
                    unset($this->mapping[$table][$toColumn]);
                } else {
                    $this->mapping[$table][$toColumn] = $fromColumn;
                }
            }
        }
    }
    
    /**
     * Register mapping overrides (use null values to remove mappings)
     * 
     * @param string $table    Table
     * @param array  $mappings Overrides
     * 
     * @return void
     */
    public static function appendMappings($table, array $mappings)
    {
        self::$mappingOverrides[] = array($table, $mappings);
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
    public function run()
    {
        $this->init();
        
        $methods = get_class_methods($this);
        foreach ($methods as $method) {
            if (substr($method, 0, 7) == 'migrate') {
                call_user_func(array($this, $method));
            }
        }
        
        $this->commitQueries();
    }

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
     * Create an insert query out of the given parameters - each of the strings can
     * contain placeholders, which will be replaced by the given $vars
     * 
     * <example>
     * $where = 'storage = :storageUid';
     * $vars = array('storage' => 1);
     * </example>
     * 
     * @param string $to    To table
     * @param string $from  From table
     * @param string $where WHERE condition
     * @param string $order ORDER statement (including ASC/DESC)
     * @param array  $vars  Variables to bind to the query (will be quoted into the
     *                      query by replacing their keys prefix with a :)
     * 
     * @return void
     */
    protected function createInsertQuery(
        $to, $from, $where = '1', $order = null, array $vars = array()
    ) {
        if (!array_key_exists($to, $this->mapping)) {
            throw new Exception\Error("No mapping for table {$to} found");
        }
            
        $mapping = $this->mapping[$to];
        $nt = PHP_EOL . '  ';
        $sql = "INSERT INTO {$to} ({$nt}";
        $sql .= implode(",{$nt}", array_keys($mapping));
        $sql .= "\n) \nSELECT";
        
        foreach ($mapping as $toColumn => $fromColumn) {
            $sql .= "{$nt} {$fromColumn} {$toColumn},";
        }
        
        $sql = rtrim($sql, ',') . " \nFROM $from \nWHERE $where";
        if ($order) {
            $sql .= " \nORDER BY $order";
        }
        $sql .= ';';
        
        return $vars ? $this->quoteInto($vars, $sql) : $sql;
    }
    
    /**
     * Similar to {@see self::createInsertQuery()} but creates an UPDATE query
     * 
     * @param string $table The table to update
     * @param string $from  Tables to select from
     * @param string $where WHERE part
     * @param array  $vars  Variables to bind to the query (will be quoted into the
     *                      query by replacing their keys prefix with a :)
     * 
     * @throws Exception\Error
     * 
     * @return type
     */
    protected function createUpdateQuery(
        $table, $from, $where, array $vars = array()
    ) {
        if (!array_key_exists($table, $this->mapping)) {
            throw new Exception\Error("No mapping for table {$table} found");
        }
            
        $mapping = $this->mapping[$table];
        $sql = "UPDATE {$table}, {$from} SET";
        $nt = PHP_EOL . '  ';
        foreach ($mapping as $toColumn => $fromColumn) {
            $sql .= "{$nt} {$table}.{$toColumn} = {$fromColumn},";
        }
        $sql = rtrim($sql, ',') . " \nWHERE $where;";
        
        return $vars ? $this->quoteInto($vars, $sql) : $sql;
    }


    /**
     * Quote values into a statement
     * 
     * @param array  $values    Values, where key must be the name
     * @param string $statement Statement string
     * 
     * @return string
     */
    protected function quoteInto(array $values, $statement)
    {
        uksort(
            $values,
            function ($a, $b) {
                return strlen($a) < strlen($b);
            }
        );
        
        $search = array();
        $replace = array();
        foreach ($values as $key => $value) {
            $search[] = ':' . $key;
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            } elseif (is_numeric($value)) {
                $value = is_int($value) ? (int) $value : (float) $value;
            } elseif ($value === null) {
                $value = 'NULL';
            } else {
                $value = $this->database->fullQuoteStr($value, '');
            }
            $replace[] = $value;
        }
        return str_replace($search, $replace, $statement);
    }
    
    /**
     * Collect a query for later execution
     * 
     * @param string $sql     SQL
     * @param string $comment Comment, output before SQL
     * 
     * @return void
     */
    protected function query($sql, $comment = null)
    {
        $this->queries[] = array($sql, $comment);
    }

    /**
     * Put the collected queries out
     * 
     * @return void
     */
    protected function dumpQueries()
    {
        foreach ($this->queries as $query) {
            list($sql, $comment) = $query;
            if ($comment) {
                $this->outputLine(preg_replace('/^(.*)$/m', '# $1', $comment));
            }
            $this->outputLine($sql . PHP_EOL);
        }
    }

    /**
     * Execute a bunch of queries and roll 'em back when an error occurs
     * 
     * @throws Exception\Error
     * 
     * @return void
     */
    protected function commitQueries()
    {
        if ($this->dryrun) {
            return $this->dumpQueries();
        }
        
        $driver = $this->database->getDatabaseHandle();
        if (!$driver instanceof \mysqli) {
            throw new Exception\Error('Driver is not of expected type');
        }
        
        $result = $driver->query("SELECT @@autocommit");
        $row = $result->fetch_row();
        $autocommit = $row[0];
        $result->free();
        
        $driver->autocommit(false);
        
        while ($query = array_shift($this->queries)) {
            list($sql, $comment) = $query;
            $this->output($comment);
            if (!$driver->query($sql)) {
                $msg = "MySQL-Error (Code {$driver->errno}): {$driver->error}";
                if (!$driver->rollback()) {
                    $msg .= "\nCOULD NOT EVEN ROLL BACK PREVIOUS QUERIES";
                } else {
                    $msg .= "\nAll previous queries rolled back";
                }
                $msg .= "\n--------\nQuery was:\n" . $sql;
                $driver->autocommit($autocommit);
                $this->outputLine();
                throw new Exception\Error($msg);
            }
            $this->outputLine(" ({$driver->affected_rows} rows affected)");
        }
        
        $driver->commit();
        $driver->autocommit($autocommit);
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

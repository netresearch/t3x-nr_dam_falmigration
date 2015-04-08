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

use TYPO3\CMS\Core\Utility\GeneralUtility;

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
    const PRE_MIGRATE_SIGNAL = 'preMigrate';
    const POST_MIGRATE_SIGNAL = 'postMigrate';
    const CREATE_INSERT_QUERY_SIGNAL = 'createInsertQuery';
    const CREATE_UPDATE_QUERY_SIGNAL = 'createUpdateQuery';
    
    /**
     * @var string
     */
    protected $response = '';

    /**
     * @var bool
     */
    protected $flushOutputs = true;
    
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
     */
    protected $storageRepository;
    
    /**
     * @var boolean
     */
    protected $dryrun = false;

    /**
     * @var bool
     */
    protected $count = false;
    
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
     * @var \TYPO3\CMS\Extbase\SignalSlot\Dispatcher
     */
    protected $singalSlotDispatcher;
    
    /**
     * Construct - set DB, apply mappings and call init
     */
    public function __construct()
    {
        $this->database = $GLOBALS['TYPO3_DB'];
        $this->storageRepository = GeneralUtility::makeInstance(
            'TYPO3\CMS\Core\Resource\StorageRepository'
        );

        $this->singalSlotDispatcher = GeneralUtility::makeInstance(
            'TYPO3\\CMS\\Extbase\\Object\\ObjectManager'
        )->get('TYPO3\\CMS\\Extbase\\SignalSlot\\Dispatcher');
        
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
        
        $this->init();
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
     * Dispatch a signal
     *
     * @param string $signal The signal name
     * @param mixed  $args   Additional args to pass to slot
     *
     * @return void
     */
    protected function emitSignal($signal, array $args = array())
    {
        return $this->singalSlotDispatcher->dispatch(
            __CLASS__, $signal, array($this, $args)
        );
    }

    /**
     * Called at last by controller or sth.
     * 
     * @return void|array
     */
    public function run()
    {
        $this->emitSignal(self::PRE_MIGRATE_SIGNAL);

        $methods = get_class_methods($this);
        foreach ($methods as $method) {
            if (substr($method, 0, 7) == 'migrate') {
                call_user_func(array($this, $method));
            }
        }
        foreach ($methods as $method) {
            if (substr($method, 0, 8) == 'sanitize') {
                call_user_func(array($this, $method));
            }
        }

        if ($this->count) {
            $res = $this->executeCountQueries();
        } elseif ($this->dryrun) {
            $res = $this->dumpQueries();
        } else {
            $res = $this->commitQueries();
        }

        $args = array(&$res);
        $this->emitSignal(self::POST_MIGRATE_SIGNAL, $args);

        return $res;
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
     * isDryrun
     *
     * @return boolean
     */
    public function isDryrun()
    {
        return $this->dryrun;
    }

    /**
     * Set whether to only count potentially affected records
     * When set to true, $this->createInsertQuery() and $this->createUpdateQuery()
     * create COUNT(*) queries, only queries beginning with COUNT( will be executed
     * and the results returned by $this->run()
     *
     * @param boolean $count Whether to only COUNT(*)
     *
     * @return $this
     */
    public function setCount($count)
    {
        $this->count = $count;
        return $this;
    }

    /**
     * isCount
     *
     * @return boolean
     */
    public function isCount()
    {
        return $this->count;
    }

    /**
     * Get the storages - either limited to storageUid or all Local ones
     *
     * @throws Exception\IllegalDriverType
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
     * Create an insert query (or a count query if $this->count)
     * out of the given parameters - each of the strings can
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
     * @throws Exception\Error
     *
     * @return string
     */
    protected function createInsertQuery(
        $to, $from, $where = '1', $order = null, array $vars = array()
    ) {
        $this->emitSignal(
            self::CREATE_INSERT_QUERY_SIGNAL,
            array(
                'to' => $to,
                'from' => &$from,
                'where' => &$where,
                'order' => &$order,
                'vars' => &$vars,
            )
        );

        if (!array_key_exists($to, $this->mapping)) {
            throw new Exception\Error("No mapping for table {$to} found");
        }

        if ($this->count) {
            $sql = "SELECT COUNT(*) FROM {$from} WHERE {$where};";
        } else {
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
        }
        
        return $this->quoteInto($vars, $sql);
    }

    /**
     * Create an update query (or a count query if $this->count)
     *
     * @param string $table The table(s)
     * @param array  $data  The data (key value pairs or just string values if you
     *                      want to set sth. to an SQL expression)
     * @param int    $where The where
     * @param array  $vars  Vars to bind to
     *
     * @return string
     */
    protected function createUpdateQuery(
        $table, array $data, $where = 1, array $vars = array()
    ) {
        $args = compact('table', 'data', 'where', 'vars');
        $this->emitSignal(self::CREATE_UPDATE_QUERY_SIGNAL, $args);

        if ($this->count) {
            $sql = "SELECT COUNT(*) FROM {$table} WHERE {$where};";
        } else {
            $sql = "UPDATE {$table} SET \n";
            foreach ($data as $key => $value) {
                if (is_numeric($key)) {
                    $sql .= $value;
                } else {
                    $sql .= "  {$key} = "
                        . $this->database->fullQuoteStr($value, '');
                }
                $sql .= ",\n";
            }
            $sql = rtrim($sql, ",\n") . " \nWHERE {$where};";
        }

        return $this->quoteInto($vars, $sql);
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
        $mappedTables = array();
        foreach (self::$mappingOverrides as $override) {
            if (array_key_exists($override[0], $mappedTables)) {
                continue;
            }
            $mappedTables[$override[0]] = 1;
            foreach ($override[1] as $column => $value) {
                $values['default(' . $override[0] . '.' . $column . ')'] = $value;
            }
        }

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
            } elseif (substr($key, 0, 8) !== 'default(') {
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
     * @return array The ran queries
     */
    protected function commitQueries()
    {        
        $driver = $this->database->getDatabaseHandle();
        if (!$driver instanceof \mysqli) {
            throw new Exception\Error('Driver is not of expected type');
        }
        
        $result = $driver->query("SELECT @@autocommit");
        $row = $result->fetch_row();
        $autocommit = $row[0];
        $result->free();
        
        $driver->autocommit(false);

        $ranQueries = array();
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
            $ranQueries[] = $sql;
            $this->outputLine(" ({$driver->affected_rows} rows affected)");
        }
        
        $driver->commit();
        $driver->autocommit($autocommit);

        return $ranQueries;
    }

    /**
     * Execute all COUNT(*) queries and ignore the rest
     *
     * @return array
     */
    protected function executeCountQueries()
    {
        $counts = array();
        while ($query = array_shift($this->queries)) {
            list($sql, $comment) = $query;
            if (strpos($sql, 'SELECT COUNT(') === 0) {
                $resultSet = $this->database->sql_query($sql);
                if ($resultSet !== false) {
                    list($count) = $this->database->sql_fetch_row($resultSet);
                    $counts[$comment] += (int) $count;
                    $this->database->sql_free_result($resultSet);
                }
            }
        }
        return $counts;
    }

    /**
     * Set wether to flush outputs immediately or wether to keep them in the response
     * variable
     *
     * @param boolean $flag Flag
     *
     * @return $this
     */
    public function setFlushOutputs($flag)
    {
        $this->flushOutputs = $flag;
        return $this;
    }

    /**
     * Get the response
     *
     * @return string
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Outputs specified text to the console window
     * You can specify arguments that will be passed to the text via sprintf
     * 
     * @param string $text      Text to output
     * @param array  $arguments Optional arguments to use for sprintf
     *
     * @see http://www.php.net/sprintf
     * 
     * @return void
     */
    public function output($text, array $arguments = array())
    {
        if ($arguments !== array()) {
            $text = vsprintf($text, $arguments);
        }
        if ($this->flushOutputs) {
            echo $text;
        } else {
            $this->response .= $text;
        }
    }

    /**
     * Outputs specified text to the console window and appends a line break
     *
     * @param string $text      Text to output
     * @param array  $arguments Optional arguments to use for sprintf
     * 
     * @return string
     */
    public function outputLine($text = '', array $arguments = array())
    {
        return $this->output($text . PHP_EOL, $arguments);
    }
}
?>

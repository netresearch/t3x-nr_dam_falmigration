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
class FileMigrationService extends AbstractService
{
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
     * Register mapping overrides (use null values to remove mappings)
     * 
     * @param string $table     Table
     * @param array  $overrides Overrides
     * 
     * @return void
     */
    public static function appendMappings($table, array $overrides)
    {
        self::$mappingOverrides[] = array($table, $overrides);
    }

    /**
     * Initialisation: Apply mapping overrides
     * 
     * @return void
     */
    protected function init()
    {
        parent::init();
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
     * Run the migration
     * 
     * @return void
     */
    public function run()
    {
        $this->query(
            'TRUNCATE TABLE sys_file;',
            'Removing existing FAL file records'
        );
        foreach ($this->getStorages() as $storage) {
            $this->query(
                $this->getMigrateFilesQuery($storage),
                "Migrating files for storage '{$storage->getName()}' "
                . "({$storage->getUid()})"
            );
        }
        
        $this->query(
            'TRUNCATE TABLE sys_file_metadata;',
            'Removing existing FAL metadata'
        );
        $this->query(
            $this->getMigrateMetadataQuery(),
            'Migrating metadata'
        );
        
        $this->query(
            'TRUNCATE TABLE sys_file_reference;',
            'Removing existing FAL references'
        );
        $this->query(
            $this->getMigrateRelationsQuery(),
            'Migrating references'
        );
        
        if (!$this->dryrun) {
            $this->commitQueries();
        } else {
            $this->dumpQueries();
        }
    }

    /**
     * Get the query to migrate all files suitable for a storage from DAM
     * 
     * @param ResourceStorage $storage Storage
     * 
     * @return string
     */
    protected function getMigrateFilesQuery(ResourceStorage $storage)
    {
        $baseDir = $storage->getPublicUrl($storage->getRootLevelFolder());
        $baseDirLen = strlen($baseDir);
        
        return $this->createInsertQuery(
            'sys_file',
            'tx_dam',
            "tx_dam.deleted = 0 AND SUBSTRING(tx_dam.file_path, 1, :baseDirLen) "
            . "= :baseDir",
            'tx_dam.uid ASC',
            array(
                'baseDir' => $baseDir,
                'baseDirLen' => $baseDirLen,
                'storageUid' => $storage->getUid()
            )
        );
    }
    
    /**
     * Get the query to migrate all the metadata
     * 
     * @return string
     */
    protected function getMigrateMetadataQuery()
    {
        return $this->createInsertQuery(
            'sys_file_metadata',
            'sys_file, tx_dam',
            'sys_file._migrateddamuid = tx_dam.uid',
            'sys_file.uid ASC'
        );
    }
    
    /**
     * Create the query to insert relations from DAM to FAL
     * 
     * @return string
     */
    protected function getMigrateRelationsQuery()
    {
        return $this->createInsertQuery(
            'sys_file_reference',
            'tx_dam_mm_ref mm, sys_file sf',
            'sf._migrateddamuid = mm.uid_local'
        );
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
        $to, $from, $where, $order = null, array $vars = array()
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
}
?>

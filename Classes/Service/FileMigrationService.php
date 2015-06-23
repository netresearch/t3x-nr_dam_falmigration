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

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

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
class FileMigrationService extends AbstractMigrationService
{
    /**
     * Override parent main method in order to install required extensions upfront
     *
     * @return array|void
     */
    public function run()
    {
        if (!ExtensionManagementUtility::isLoaded('filemetadata')) {
            $this->output('Installing required extension filemetadata ');
            if ($this->isDryrun()) {
                $this->outputLine('skipped while dry run');
            } else {
                GeneralUtility::makeInstance(
                    'TYPO3\\CMS\\Extbase\\Object\\ObjectManager'
                )
                    ->get('TYPO3\\CMS\\Extensionmanager\\Utility\\InstallUtility')
                    ->install('filemetadata');
                $this->outputLine('successful');
            }
        }

        return parent::run();
    }


    /**
     * Migrate all files by storage from DAM
     * 
     * @return void
     */
    protected function migrateFiles()
    {
        foreach ($this->getStorages() as $storage) {
            $baseDir = $storage->getPublicUrl($storage->getRootLevelFolder());
            $baseDirLen = strlen($baseDir);
            $this->query(
                $this->createInsertQuery(
                    'sys_file',
                    'tx_dam '
                    . 'LEFT JOIN sys_file sf2 ON (tx_dam.uid = sf2._migrateddamuid)',
                    "sf2.uid IS NULL AND tx_dam.deleted = 0 AND "
                    . "SUBSTRING(tx_dam.file_path, 1, :baseDirLen) = :baseDir",
                    'tx_dam.uid ASC',
                    array(
                        'baseDir' => $baseDir,
                        'baseDirLen' => $baseDirLen,
                        'storageUid' => $storage->getUid()
                    )
                ),
                "Migrating files for storage '{$storage->getName()}' "
                . "({$storage->getUid()})"
            );
        }
    }
    
    /**
     * Migrate all the metadata
     * 
     * @return void
     */
    protected function migrateMetadata()
    {
        $this->query(
            $this->createInsertQuery(
                'sys_file_metadata',
                'tx_dam, sys_file '
                . 'LEFT JOIN sys_file_metadata sfm2 ON (sys_file.uid = sfm2.file)',
                'sfm2.uid IS NULL AND sys_file._migrateddamuid = tx_dam.uid',
                'sys_file.uid ASC'
            ),
            'Migrating metadata'
        );
    }
    
    /**
     * Insert relations from DAM to FAL
     * 
     * @return void
     */
    protected function migrateReferences()
    {
        $this->query(
            $this->createInsertQuery(
                'sys_file_reference',
                'tx_dam_mm_ref mm, sys_file sf '
                . 'LEFT JOIN sys_file_reference sfr2 ON (sf.uid = sfr2.uid_local)',
                'sfr2.uid IS NULL AND sf._migrateddamuid = mm.uid_local'
            ),
            'Migrating references'
        );
    }

    /**
     * Rewrite <media> tags to <link> tags
     *
     * @return void
     */
    public function migrateMediaTags()
    {
        $sql = 'SELECT '
            . 'tablename, GROUP_CONCAT(DISTINCT field) fields, recuid '
            . 'FROM sys_refindex '
            . "WHERE ref_table = 'tx_dam' AND softref_key='mediatag' "
            . 'GROUP BY tablename, recuid;';

        if ($this->count) {
            $sql = 'SELECT COUNT(*) FROM (' . rtrim($sql, ' ;') . ') tmp';
            $this->query($sql);
            return;
        }

        $res = $this->database->sql_query($sql);
        $warnings = array();
        while ($ref = $this->database->sql_fetch_assoc($res)) {
            $where = 'uid=' . $ref['recuid'];
            $row = $this->database->exec_SELECTgetSingleRow(
                $ref['fields'], $ref['tablename'], $where
            );
            if (!$row) {
                $warnings[] = "Missing record {$ref['tablename']}:{$ref['recuid']}";
                continue;
            }
            $newRow = array();
            foreach ($row as $field => $content) {
                preg_match_all(
                    '#(<|&lt;)media ([0-9]+?)([^>]*)?(>|&gt;)(.*)\1/media\4#U',
                    $content, $results, PREG_SET_ORDER
                );
                foreach ($results as $result) {
                    $fileRow = $this->database->exec_SELECTgetSingleRow(
                        'uid', 'sys_file', '_migrateddamuid=' . $result[2]
                    );
                    if ($fileRow) {
                        $newRow[$field] = $content = str_replace(
                            $result[0],
                            $result[1] . 'link file:' . $fileRow['uid']
                            . $result[3] . $result[4] . $result[5]
                            . $result[1] . '/link' . $result[4],
                            $content
                        );
                    } else {
                        $warnings[] = 'No FAL file found for dam uid: ' . $result[2];
                    }
                }
            }
            if ($newRow) {
                $this->query(
                    $this->createUpdateQuery($ref['tablename'], $newRow, $where),
                    "Migrating <media> tags in {$ref['tablename']}:{$ref['recuid']}"
                );
            }
        }
        $this->outputWarnings($warnings);
    }
    
    /**
     * Set foreign fields of 'inline' relations to 1, as TYPO3 otherwise won't show
     * the relations in TCE forms
     * 
     * @global type $TCA
     * 
     * @return void
     */
    public function sanitizeRelatedRecords()
    {
        global $TCA;
        \t3lib_extMgm::loadBaseTca();

        $warnings = array();
        foreach ($this->getRelatedTableFields() as $table => $fields) {
            if (array_key_exists($table, $TCA)) {
                $dbFields = $this->database->admin_get_fields($table);
                foreach ($fields as $field) {
                    if (array_key_exists($field, $TCA[$table]['columns'])) {
                        if (!$TCA[$table]['columns']['config']['type'] != 'inline') {
                            $warnings[] = "Referenced field not configured as "
                            . "'inline' field in \$TCA: $table.$field";
                            continue;
                        }
                    } else {
                        $warnings[] = 'Referenced field not configured in $TCA: '
                            . "$table.$field";
                            continue;
                    }
                    if (array_key_exists($field, $dbFields)) {
                        $this->query(
                            $this->createUpdateQuery(
                                "$table tt, tx_dam_mm_ref mm, sys_file sf",
                                array('tt.' . $field => 1),
                                "tt.$field != 1 AND "
                                . "mm.uid_local = sf._migrateddamuid AND "
                                . "mm.uid_foreign = tt.uid AND "
                                . "mm.tablenames = '$table' AND "
                                . "mm.ident = '$field';"
                            ),
                            "Setting relations on local field $table.$field"
                        );
                    } else {
                        $warnings[] = "Local field doesn't exist:  $table.$field";
                    }
                }
            } else {
                $warnings[] = "Referenced table doesn't exist in \$TCA:  $table";
            }
        }
        $this->outputWarnings($warnings);
    }

    /**
     * The sys_file_reference records need the PIDs to be the same as those of
     * the foreign records (or UID when foreign table is pages)
     * We couldn't handle this while import and thus have to do this here.
     *
     * @return void
     */
    public function sanitizeReferencePids()
    {
        $refTable = 'sys_file_reference sfr';
        $res = $this->database->exec_SELECTquery('tablenames', $refTable, '1', 'tablenames');
        while ($row = $this->database->sql_fetch_row($res)) {
            list($table) = $row;
            $refWhere = 'sfr.tablenames = ' . $this->database->fullQuoteStr($table, '');
            if ($table === 'pages') {
                $query = $this->createUpdateQuery(
                    $refTable,
                    array('pid = uid_foreign'),
                    $refWhere
                );
            } else {
                $query = $this->createUpdateQuery(
                    "$refTable, $table t",
                    array('sfr.pid = t.pid'),
                    $refWhere . ' AND t.uid = sfr.uid_foreign'
                );
            }
            $this->query($query, 'Setting reference pids for files on ' . $table);
        }
    }

    /**
     * Get the tables (keys) and fields (values) of the related records
     *
     * @return array
     */
    protected function getRelatedTableFields()
    {
        $tablesAndFields = $this->database->exec_SELECTgetRows(
            'tablenames, ident',
            'tx_dam_mm_ref',
            '1',
            'tablenames, ident'
        );
        $tableFields = array();
        foreach ($tablesAndFields as $tableAndField) {
            if (!array_key_exists($tableAndField['tablenames'], $tableFields)) {
                $tableFields[$tableAndField['tablenames']] = array();
            }
            $tableFields[$tableAndField['tablenames']][] = $tableAndField['ident'];
        }
        return $tableFields;
    }

    /**
     * Output the warnings
     *
     * @param array $warnings The warnings
     *
     * @return void
     */
    protected function outputWarnings(array $warnings)
    {
        if ($warnings) {
            if ($this->dryrun) {
                $this->outputLine('/*');
            }
            foreach ($warnings as $warning) {
                $this->outputLine('[WARNING] ' . $warning);
            }
            if ($this->dryrun) {
                $this->outputLine('*/');
            }
        }
    }
}
?>

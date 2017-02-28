<?php

/**
 * See class comment
 *
 * PHP version 5
 *
 * @category   Netresearch
 * @package    NR_DAM_FALMIGRATION
 * @subpackage Service
 * @author     Axel Kummer <axel.kummer@netresearch.de>
 * @license    http://www.netresearch.de Netresearch
 * @link       http://www.netresearch.de
 */

namespace Netresearch\NrDamFalmigration\Service;

/**
 * Service to migrate the group and user permissions from DAM to FAL
 *
 * @category   Netresearch
 * @package    NR_DAM_FALMIGRATION
 * @subpackage Service
 * @author     Axel Kummer <axel.kummer@netresearch.de>
 * @license    http://www.netresearch.de Netresearch
 * @link       http://www.netresearch.de
 */
class PermissionMigrationService extends AbstractMigrationService
{
    /**
     * Migrates the permissions from DAM to FAL in be_groups
     *
     * @return void
     */
    public function migrateBeGroupsPermissions()
    {
        $this->outputLine('Migrate Permissions for be_users');

        if ($this->isDryrun()) {
            $this->outputLine('Only show queries which should be performed.');
        }

        $arGroups = $this->getGroupsWithDamPermissions();

        $this->outputLine(count($arGroups). ' entires have Permissions');

        foreach ($arGroups as $group) {
            $this->outputLine(
                'Migrating :' . $group['title'] . ' ('. $group['uid'] . ')'
            );
            $this->updateGroupPermissions($group);
        }
    }

    /**
     * Update the permissions of a group
     *
     * @param array $group Group dataset
     *
     * @return void
     */
    protected function updateGroupPermissions($group)
    {
        $updatedGroup = array();

        foreach ($group as $field => $value) {
            if (in_array($field, $this->getFieldsContainingPermissions())) {
                $updatedGroup[$field] = $this->getUpdatedField($field, $value);
            } else {
                $updatedGroup[$field] = $value;
            }
        }

        var_dump($updatedGroup);
    }

    /**
     * Replaces the dam permission with corresponding fal permissions
     *
     * @param string $field name of database field
     * @param string $value Value of database field
     *
     * @return string
     */
    protected function getUpdatedField($field, $value)
    {
        $arPermissions = explode(',', $value);

        $arReplace = array(
            '/^tx_dam$/' => 'sys_file,sys_file_metadata',
            '/^tx_dam_cat$/' => 'sys_category',
            '/^tx_dam_mm_cat$/' => 'sys_category_record_mm',
            '/^tx_dam_mm_ref$/' => 'sys_file_reference',
            '/^tx_dam_selection$/' => 'sys_file_collection',
            '/^txdamM1$/' => null,
            '/^txdamM1_file$/' => 'file',
            '/^txdamM1_list$/' => 'file_list',
        );

        $arPermissions = preg_replace(
            array_keys($arReplace), $arReplace, $arPermissions
        );

        foreach ($arPermissions as $index => $permission) {
            if (empty($permission)) {
                unset($arPermissions[$index]);
            }
        }

        return implode(',', $arPermissions);
    }

    /**
     * Returns the groups which have dam permissions
     *
     * @return array|bool
     */
    protected function getGroupsWithDamPermissions()
    {
        $arGroups = array();

        $result = $this->database->exec_SELECTquery(
            '*', 'be_groups', $this->getWhereClause()
        );

        while ($row = $this->database->sql_fetch_assoc($result)) {
            $arGroups[] = $row;
        }

        return $arGroups;
    }

    /**
     * Returns where clause to fetch the groups
     *
     * @return string
     */
    protected function getWhereClause()
    {
        $arWhere = array();

        foreach ($this->getFieldsContainingPermissions() as $field) {
            $arWhere[] = $field . ' LIKE "%tx_dam%" OR ' . $field . ' LIKE "%txdam%"';
        }

        return implode(' OR ', $arWhere);
    }

    /**
     * Returns an array with fields which contains dam permissions
     *
     * @return array
     */
    protected function getFieldsContainingPermissions()
    {
        $arFields = array(
            'non_exclude_fields',
            'tables_select',
            'tables_modify',
            'groupMods',
            'explicit_allowdeny',
            'tx_dam_mountpoints',
        );

        return $arFields;
    }


}
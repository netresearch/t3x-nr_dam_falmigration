<?php
/**
 * Extension information
 *
 * PHP version 5
 *
 * @category Netresearch
 * @package  NR_DAM_FALMIGRATION
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch
 * @link     http://www.netresearch.de
 */

$EM_CONF[$_EXTKEY] = array(
    'title' => 'DAM to FAL migration',
    'description' => 'Tools to migrate from Digital Asset Management (DAM) to File '
    . 'Abstraction Layer (FAL)',
    'category' => 'services',
    'author' => 'Christian Opitz',
    'author_company' => 'Netresearch GmbH & Co. KG',
    'author_email' => 'christian.opitz@netresearch.de',
    'shy' => '',
    'constraints' => array(
        'depends' => array(
            'typo3' => '4.2.0-6.2.99',
        ),
        'conflicts' => array(
        ),
        'suggests' => array(
        ),
    ),
    'conflicts' => '',
    'priority' => '',
    'module' => '',
    'state' => 'stable',
    'internal' => '',
    'uploadfolder' => 0,
    'createDirs' => '',
    'modify_tables' => '',
    'clearCacheOnLoad' => 0,
    'lockType' => '',
    'version' => '1.2.0',
    '_md5_values_when_last_written' => '',
    'suggests' => array(
    ),
);

?>

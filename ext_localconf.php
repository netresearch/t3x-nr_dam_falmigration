<?php
/**
 * Extension configuration
 *
 * PHP version 5
 *
 * @category Netresearch
 * @package  NR_DAM_FALMIGRATION
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  http://www.netresearch.de Netresearch
 * @link     http://www.netresearch.de
 */

if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}

if (TYPO3_MODE == 'BE') {
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers'][]
        = 'Netresearch\\NrDamFalmigration\Controller\DamMigrationCommandController';
    
    Netresearch\NrDamFalmigration\Service\FileMigrationService::appendMappings(
        'sys_file',
        array(
            'tstamp' => 'UNIX_TIMESTAMP()',
            'storage' => ':storageUid',
            'type' => 
                'IF(tx_dam.media_type >= 0 AND '
                . 'tx_dam.media_type <= 5, tx_dam.media_type, 5)',
            'identifier' => 
                '@filepath := CONCAT(@folderpath := SUBSTRING(tx_dam.file_path, '
                . ':baseDirLen, CHAR_LENGTH(tx_dam.file_path) - :baseDirLen), '
                . '\'/\', tx_dam.file_name)',
            'identifier_hash' => 'SHA1(@filepath) as',
            'folder_hash' => 'SHA1(@folderpath) as',
            'extension' => 'tx_dam.file_type',
            'mime_type' => 
                'CONCAT(tx_dam.file_mime_type, \'/\', tx_dam.file_mime_subtype)',
            'name' => 'tx_dam.file_name',
            'size' => 'tx_dam.file_size',
            'creation_date' => 'tx_dam.file_ctime',
            'modification_date' => 'tx_dam.file_mtime',
            '_migrateddamuid' => 'tx_dam.uid'
        )
    );
    Netresearch\NrDamFalmigration\Service\FileMigrationService::appendMappings(
        'sys_file_metadata',
        array(
            'l10n_diffsource' => 'l18n_diffsource',
            'file' => 'sys_file.uid',
            'tstamp' => 'UNIX_TIMESTAMP()',
            'crdate' => 'UNIX_TIMESTAMP()',
            'title' => 'tx_dam.title',
            'alternative' => 'tx_dam.alt_text',
            'categories' => 'tx_dam.category',
            'description' => 'tx_dam.description',
            'width' => 'tx_dam.hpixels',
            'height' => 'tx_dam.vpixels',
            'visible' => 'tx_dam.hidden',
            'fe_groups' => 'tx_dam.fe_group',
            'download_name' => 'tx_dam.file_dl_name',
            'source' => 'tx_dam.ident',
            'creator' => 'tx_dam.creator',
            'publisher' => 'tx_dam.publisher',
            'keywords' => 'tx_dam.keywords',
            'caption' => 'tx_dam.caption',
            'note' => 'tx_dam.instructions',
            'content_creation_date' => 'tx_dam.date_cr',
            'content_modification_date' => 'tx_dam.date_mod',
            'location_country' => 'tx_dam.loc_country',
            'location_city' => 'tx_dam.loc_city',
            'language' => 'tx_dam.language',
            'color_space' => 'tx_dam.color_space',
            'unit' => 'tx_dam.height_unit',
            'pages' => 'tx_dam.pages'
        )
    );
}
?>
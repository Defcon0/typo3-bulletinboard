<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

call_user_func(function () {
    ExtensionUtility::registerPlugin(
        'WsBulletinboard',
        'List',
        'LLL:EXT:ws_bulletinboard/Resources/Private/Language/locallang_db.xlf:wsbulletinboard_list'
    );

    ExtensionUtility::registerPlugin(
        'WsBulletinboard',
        'Latest',
        'LLL:EXT:ws_bulletinboard/Resources/Private/Language/locallang_db.xlf:wsbulletinboard_latest'
    );

    ExtensionUtility::registerPlugin(
        'WsBulletinboard',
        'Form',
        'LLL:EXT:ws_bulletinboard/Resources/Private/Language/locallang_db.xlf:wsbulletinboard_form'
    );

    /* Flexform setting  */
    $pluginSignatureform = 'wsbulletinboard_form';
    $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist'][$pluginSignatureform] = 'pi_flexform';
    ExtensionManagementUtility::addPiFlexFormValue($pluginSignatureform, 'FILE:EXT:ws_bulletinboard/Configuration/FlexForm/form.xml');

    $pluginSignatureform = 'wsbulletinboard_list';
    $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist'][$pluginSignatureform] = 'pi_flexform';
    ExtensionManagementUtility::addPiFlexFormValue($pluginSignatureform, 'FILE:EXT:ws_bulletinboard/Configuration/FlexForm/list.xml');

    $pluginSignatureform = 'wsbulletinboard_latest';
    $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist'][$pluginSignatureform] = 'pi_flexform';
    ExtensionManagementUtility::addPiFlexFormValue($pluginSignatureform, 'FILE:EXT:ws_bulletinboard/Configuration/FlexForm/latest.xml');

});

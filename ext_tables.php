<?php
if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}

/*
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr(
    'tx_wsbulletinboard_domain_model_entry',
    'EXT:ws_bulletinboard/Resources/Private/Language/locallang_csh_tx_wsbulletinboard_domain_model_entry.xlf'
);*/

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('tx_wsbulletinboard_domain_model_entry');

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig(
    '<INCLUDE_TYPOSCRIPT: source="FILE:EXT:ws_bulletinboard/Configuration/TSconfig/ContentElementWizard.tsconfig">'
);

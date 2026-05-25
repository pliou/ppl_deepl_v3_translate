<?php

defined('TYPO3') or die();

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

ExtensionUtility::registerPlugin(
    'PplDeeplV3Translate',
    'Deepl',
    'LLL:EXT:ppl_deepl_v3_translate/Resources/Private/Language/locallang.xlf:plugin.v3.text.title'
);

ExtensionManagementUtility::addStaticFile(
    'ppl_deepl_v3_translate',
    'Configuration/TypoScript',
    'LLL:EXT:ppl_deepl_v3_translate/Resources/Private/Language/locallang.xlf:plugin.v3.static.title'
);

ExtensionUtility::registerPlugin(
    'PplDeeplV3Translate',
    'DeeplFile',
    'LLL:EXT:ppl_deepl_v3_translate/Resources/Private/Language/locallang.xlf:plugin.v3.file.title'
);

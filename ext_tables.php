<?php

defined('TYPO3') or die();

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

ExtensionManagementUtility::addStaticFile(
    'ppl_deepl_v3_translate',
    'Configuration/TypoScript',
    'LLL:EXT:ppl_deepl_v3_translate/Resources/Private/Language/locallang.xlf:plugin.v3.static.title'
);

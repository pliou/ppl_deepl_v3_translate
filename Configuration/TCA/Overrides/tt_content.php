<?php

defined('TYPO3') or die();

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

call_user_func(function () {
    ExtensionUtility::registerPlugin(
        'PplDeeplV3Translate',
        'Deepl',
        'LLL:EXT:ppl_deepl_v3_translate/Resources/Private/Language/locallang.xlf:plugin.v3.text.title'
    );

    ExtensionUtility::registerPlugin(
        'PplDeeplV3Translate',
        'Deeplfile',
        'LLL:EXT:ppl_deepl_v3_translate/Resources/Private/Language/locallang.xlf:plugin.v3.file.title'
    );
});

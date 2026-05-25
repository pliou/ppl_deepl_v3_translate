<?php

defined('TYPO3') or die();

call_user_func(function () {
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPlugin(
        [
            'LLL:EXT:ppl_deepl_v3_translate/Resources/Private/Language/locallang.xlf:plugin.v3.text.title',
            'ppldeeplv3translate_deepl',
            null,
        ],
        'list_type',
        'ppl_deepl_v3_translate'
    );

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPlugin(
        [
            'LLL:EXT:ppl_deepl_v3_translate/Resources/Private/Language/locallang.xlf:plugin.v3.file.title',
            'ppldeeplv3translate_deeplfile',
            null,
        ],
        'list_type',
        'ppl_deepl_v3_translate'
    );
});

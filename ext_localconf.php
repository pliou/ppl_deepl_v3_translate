<?php

defined('TYPO3') or die();

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    'PplDeeplV3Translate',
    'Deepl',
    [\Ppl\PplDeeplV3Translate\Controller\DeeplController::class => 'interface'],
    [\Ppl\PplDeeplV3Translate\Controller\DeeplController::class => 'interface'],
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    'PplDeeplV3Translate',
    'Deeplfile',
    [\Ppl\PplDeeplV3Translate\Controller\DeeplFileController::class => 'index'],
    [\Ppl\PplDeeplV3Translate\Controller\DeeplFileController::class => 'index'],
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::PLUGIN_TYPE_PLUGIN
);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScriptSetup(
    "@import 'EXT:ppl_deepl_v3_translate/Configuration/TypoScript/setup.typoscript'"
);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig(
    "@import 'EXT:ppl_deepl_v3_translate/Configuration/TsConfig/Page/ContentElementWizard.tsconfig'"
);

$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase']['extensions']['PplDeeplV3Translate']['plugins']['Deepl']['nonCacheableActions'] = [
    'interface' => 'interface',
];

$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase']['extensions']['PplDeeplV3Translate']['plugins']['Deeplfile']['nonCacheableActions'] = [
    'index' => 'index',
];

foreach ([
    'ppl_deepl_logout',
    'ppl_deepl_logintype',
    'return_url',
    'redirect_url',
    'logintype',
] as $parameterName) {
    if (!in_array($parameterName, $GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'] ?? [], true)) {
        $GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = $parameterName;
    }
}

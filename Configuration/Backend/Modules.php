<?php

declare(strict_types=1);

use Ppl\PplDeeplV3Translate\Controller\BackendTranslationController;
use Ppl\PplDeeplV3Translate\Controller\BackendConfigurationController;

return [
    'ppl_deepl_v3' => [
        'position' => ['after' => 'system'],
        'iconIdentifier' => 'module-ppl-deepl-v3',
        'labels' => [
            'title' => 'LLL:EXT:ppl_deepl_v3_translate/Resources/Private/Language/locallang.xlf:module.root.title',
            'shortDescription' => 'LLL:EXT:ppl_deepl_v3_translate/Resources/Private/Language/locallang.xlf:module.root.description',
        ],
    ],
    'ppl_deepl_v3_configuration' => [
        'parent' => 'ppl_deepl_v3',
        'position' => ['before' => '*'],
        'access' => 'user',
        'path' => '/module/ppl-deepl-v3/configuration',
        'iconIdentifier' => 'module-ppl-deepl-v3-configuration',
        'labels' => [
            'title' => 'LLL:EXT:ppl_deepl_v3_translate/Resources/Private/Language/locallang.xlf:module.configuration.title',
            'shortDescription' => 'LLL:EXT:ppl_deepl_v3_translate/Resources/Private/Language/locallang.xlf:module.configuration.description.v3',
        ],
        'routes' => [
            '_default' => [
                'target' => BackendConfigurationController::class . '::handleRequest',
            ],
        ],
    ],
    'ppl_deepl_v3_translation' => [
        'parent' => 'ppl_deepl_v3',
        'position' => ['after' => 'ppl_deepl_v3_configuration'],
        'access' => 'user',
        'path' => '/module/ppl-deepl-v3/translation',
        'iconIdentifier' => 'module-ppl-deepl-v3-translation',
        'labels' => [
            'title' => 'LLL:EXT:ppl_deepl_v3_translate/Resources/Private/Language/locallang.xlf:module.v3.translation.title',
            'shortDescription' => 'LLL:EXT:ppl_deepl_v3_translate/Resources/Private/Language/locallang.xlf:module.v3.translation.description',
        ],
        'routes' => [
            '_default' => [
                'target' => BackendTranslationController::class . '::handleRequest',
            ],
        ],
    ],
    'ppl_deepl_v3_file_translation' => [
        'parent' => 'ppl_deepl_v3',
        'position' => ['after' => 'ppl_deepl_v3_translation'],
        'access' => 'user',
        'path' => '/module/ppl-deepl-v3/file-translation',
        'iconIdentifier' => 'module-ppl-deepl-v3-file-translation',
        'labels' => [
            'title' => 'LLL:EXT:ppl_deepl_v3_translate/Resources/Private/Language/locallang.xlf:module.v3.file.title',
            'shortDescription' => 'LLL:EXT:ppl_deepl_v3_translate/Resources/Private/Language/locallang.xlf:module.v3.file.description',
        ],
        'routes' => [
            '_default' => [
                'target' => BackendTranslationController::class . '::handleRequest',
            ],
        ],
    ],
];

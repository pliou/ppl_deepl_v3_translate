<?php

declare(strict_types=1);

use Ppl\PplDeeplV3Translate\Controller\BackendFrontendAccessController;
use Ppl\PplDeeplV3Translate\Controller\BackendTranslationController;

return [
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
            'download' => [
                'path' => '/download',
                'methods' => ['GET'],
                'target' => BackendTranslationController::class . '::downloadAction',
            ],
        ],
    ],
    'ppl_deepl_v3_frontend_access' => [
        'parent' => 'ppl_deepl_v3',
        'position' => ['after' => 'ppl_deepl_v3_file_translation'],
        'access' => 'user',
        'path' => '/module/ppl-deepl-v3/frontend-access',
        'iconIdentifier' => 'module-ppl-deepl-v3-configuration',
        'labels' => [
            'title' => 'LLL:EXT:ppl_deepl_v3_translate/Resources/Private/Language/locallang.xlf:config.frontendAccess',
            'shortDescription' => 'LLL:EXT:ppl_deepl_v3_translate/Resources/Private/Language/locallang.xlf:backend.frontendLoginApplies',
        ],
        'routes' => [
            '_default' => [
                'target' => BackendFrontendAccessController::class . '::handleRequest',
            ],
        ],
    ],
];

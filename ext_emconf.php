<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'PPL DeepL V3 Translate',
    'description' => 'TYPO3 frontend content element and backend modules for DeepL V3 text, file, glossary and style rule translation.',
    'category' => 'module',
    'author' => 'Pawel Pliousnin',
    'author_email' => 'pliousnin@ppl-ds.com',
    'state' => 'stable',
    'version' => '14.3.0',
    'clearCacheOnLoad' => 0,
    'constraints' => [
        'depends' => [
            'typo3' => '14.0.0-14.99.99',
            'backend' => '14.0.0-14.99.99',
            'extbase' => '14.0.0-14.99.99',
            'fluid' => '14.0.0-14.99.99',
            'fluid_styled_content' => '14.0.0-14.99.99',
            'frontend' => '14.0.0-14.99.99',
            'ppl_deepl_v3_requests' => '14.0.0-14.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];

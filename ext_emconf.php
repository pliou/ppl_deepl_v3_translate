<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'PPL DeepL V3 Translate',
    'description' => 'TYPO3 frontend content element and backend modules for DeepL V3 text, file, glossary and style rule translation.',
    'category' => 'module',
    'author' => 'Pawel Pliousnin',
    'author_email' => 'pliousnin@ppl-ds.com',
    'state' => 'stable',
    'version' => '12.4.1',
    'clearCacheOnLoad' => 0,
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-12.4.99',
            'backend' => '12.4.0-12.4.99',
            'extbase' => '12.4.0-12.4.99',
            'fluid' => '12.4.0-12.4.99',
            'fluid_styled_content' => '12.4.0-12.4.99',
            'frontend' => '12.4.0-12.4.99',
            'ppl_deepl_v3_requests' => '12.4.0-12.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];

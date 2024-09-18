<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Bulletinboard',
    'description' => 'Bulletinboard Extension',
    'category' => 'plugin',
    'author' => 'Sven Wappler',
    'author_email' => '',
    'author_company' => 'WapplerSystems',
    'state' => 'stable',
    'version' => '11.0.1',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-12.4.99',
            'form' => '11.5.0-11.5.99',
            'form_extended' => '11.0.0-11.99.99'
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
];

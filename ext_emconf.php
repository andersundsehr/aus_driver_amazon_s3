<?php

/** @var string $_EXTKEY */
$EM_CONF[$_EXTKEY] = [
    'title' => 'Amazon AWS S3 FAL driver (CDN)',
    'description' => 'Provides a FAL driver for the Amazon Web Service S3.',
    'category' => 'be',
    'version' => '1.11.0',
    'state' => 'stable',
    'uploadfolder' => false,
    'createDirs' => '',
    'clearcacheonload' => false,
    'author' => 'Markus Hölzle',
    'author_email' => 'typo3@markus-hoelzle.de',
    'author_company' => 'anders und sehr GmbH',
    'constraints' =>
        [
            'depends' =>
                [
                    'typo3' => '9.5.0-10.4.99',
                ],
            'conflicts' => [],
            'suggests' => [],
        ],
];

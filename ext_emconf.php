<?php

/** @var string $_EXTKEY */
$EM_CONF[$_EXTKEY] = [
    'title' => 'Amazon AWS S3 FAL driver (CDN)',
    'description' => 'Provides a FAL driver for the Amazon Web Service S3.',
    'category' => 'be',
    'version' => '1.13.500',
    'state' => 'stable',
    'clearcacheonload' => false,
    'author' => 'Markus Hölzle',
    'author_email' => 'typo3@markus-hoelzle.de',
    'author_company' => 'different.technology',
    'constraints' =>
        [
            'depends' =>
                [
                    'typo3' => '13.0.0-13.99.99',
                ],
            'conflicts' => [],
            'suggests' => [],
        ],
];

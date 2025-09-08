<?php

/** @var string $_EXTKEY */
$EM_CONF[$_EXTKEY] = [
    'title' => 'Amazon AWS S3 FAL driver (CDN)',
    'description' => 'Provides a FAL driver for the Amazon Web Service S3.',
    'category' => 'be',
    'version' => '1.12.1',
    'state' => 'stable',
    'uploadfolder' => false,
    'createDirs' => '',
    'clearcacheonload' => false,
    'author' => 'Markus Hölzle',
    'author_email' => 'typo3@markus-hoelzle.de',
    'author_company' => 'different.technology',
    'constraints' =>
        [
            'depends' =>
                [
                    'typo3' => '11.5.30-12.4.99',
                    'aws_sdk_php' => '3.356.0-3.999.999',
                ],
            'conflicts' => [],
            'suggests' => [],
        ],
];

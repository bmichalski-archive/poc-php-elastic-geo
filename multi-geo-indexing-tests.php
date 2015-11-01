<?php

require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

$inputDefinition = new InputDefinition();

$inputDefinition->addArgument(
    new InputArgument('host', InputArgument::REQUIRED)
);

$options = [
    [
        'port',
        null,
        InputOption::VALUE_REQUIRED,
        '',
        9200
    ],
    [
        'index',
        null,
        InputOption::VALUE_REQUIRED,
        '',
        'test'
    ],
    [
        'type',
        null,
        InputOption::VALUE_REQUIRED,
        '',
        'test'
    ],
];

foreach ($options as $option) {
    $inputDefinition->addOption(
        new InputOption($option[0], $option[1], $option[2], $option[3], $option[4])
    );
}

$precisions = [
    10,
    50,
    100,
    250,
    1000,
    10000,
];

$trees = [
    'quadtree',
    'geohash',
];

$bulkSizes = [
    10,
    50,
    100,
    250,
    1000,
];

$argvInput = new ArgvInput($argv, $inputDefinition);

echo implode(
    "\t",
    [
        'host',
        'port',
        'indexName',
        'typeName',
        'tree',
        'treeLevels',
        'precision',
        'duration',
        'totalFlushed',
        'bulkSize',
        'indexSize'
    ]
).PHP_EOL;

foreach ($trees as $tree) {
    foreach ($precisions as $precision) {
        foreach ($bulkSizes as $bulkSize) {
            passthru(
                sprintf(
                    'php %s %s %s --port %s --index %s --type %s --precision %s --tab --bulk-size %s',
                    __DIR__.'/geo-indexing-test.php',
                    $argvInput->getArgument('host'),
                    $tree,
                    $argvInput->getOption('port'),
                    $argvInput->getOption('index'),
                    $argvInput->getOption('type'),
                    $precision,
                    $bulkSize
                )
            );
        }
    }
}
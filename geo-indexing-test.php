<?php

require __DIR__.'/vendor/autoload.php';

use Elastica\Client;
use Elastica\Document;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

$inputDefinition = new InputDefinition();

$inputDefinition->addArgument(
    new InputArgument('host', InputArgument::REQUIRED)
);

$inputDefinition->addArgument(
    new InputArgument('tree', InputArgument::REQUIRED)
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
    [
        'treeLevels',
        null,
        InputOption::VALUE_REQUIRED,
        '',
        null
    ],
    [
        'precision',
        null,
        InputOption::VALUE_REQUIRED,
        '',
        null
    ],
    [
        'tab',
        null,
        InputOption::VALUE_NONE
    ],
    [
        'bulk-size',
        null,
        InputOption::VALUE_REQUIRED,
        '',
        10
    ],
];

foreach ($options as $option) {
    $inputDefinition->addOption(
        new InputOption($option[0], $option[1], $option[2], $option[3], $option[4])
    );
}

$argvInput = new ArgvInput($argv, $inputDefinition);

$main = function () use ($argvInput) {
    $host = $argvInput->getArgument('host');
    $port = $argvInput->getOption('port');
    $indexName = $argvInput->getOption('index');
    $typeName = $argvInput->getOption('type');
    $treeLevels = $argvInput->getOption('treeLevels');
    $precision = $argvInput->getOption('precision');
    $tree = $argvInput->getArgument('tree');
    $bulkSize = $argvInput->getOption('bulk-size');
    
    $tab = $argvInput->getOption('tab');
    
    if (!in_array($tree, [ 'quadtree', 'geohash' ]) ) {
        throw new \Exception(
            sprintf('Supported tree types are quadtree and geohash, "%s" given.', $tree)
        );
    }
    
    if (!isset($treeLevels) && !isset($precision)) {
        throw new \Exception(
            'Test requires either treeLevels or precision to be set.'
        );
    }
    
    if (isset($treeLevels) && isset($precision)) {
        throw new \Exception(
            'Test requires either treeLevels or precision to be set, not both.'
        );
    }

    if (!$tab) {
        echo sprintf(
            'Starting test on host %s:%s, using index %s and type %s, with tree %s using %s, with a bulk size of %s.',
            $host,
            $port,
            $indexName,
            $typeName,
            $tree,
            isset($treeLevels) ? 'treeLevels '.$treeLevels : 'precision '.$precision,
            $bulkSize
        ).PHP_EOL;
    }
    
    $elasticaClient = new Client(
        [
            'host' => $host,
            'port' => $port,
            'timeout' => -1,
        ]
    );

    $index = $elasticaClient->getIndex($indexName);
    
    $type = $index->getType($typeName);
    
    $file = __DIR__.'/france-geojson/departements/01/communes.geojson';
        
    if ($index->exists()) {
        $index->delete();
    }

    $geoJsonMapping = [
        'type' => 'geo_shape',
        'tree' => $tree,
    ];

    if (isset($treeLevels)) {
        $geoJsonMapping['tree_levels'] = $treeLevels;
    } else {
        $geoJsonMapping['precision'] = $precision;
    }

    $index->create(
        [
            "mappings" => [
                "test" => [
                    "dynamic" => "strict",
                    "properties" => [
                        'code' => [
                            'type' => 'string',
                            'index' => 'not_analyzed',
                        ],
                        'name' => [
                            'type' => 'string',
                            'index' => 'not_analyzed',
                        ],
                        'geoJson' => $geoJsonMapping,
                    ],
                ],
            ],
        ]
    );

    $docs = [];

    $totalFlushed = 0;

    $flush = function () use ($type, &$docs, &$totalFlushed, $tab) {
        $count = count($docs);

        if ($count > 0) {
            if (!$tab) {
                echo 'Flushing '.$count.' documents.'.PHP_EOL;
            }

            $type->addDocuments($docs);

            $totalFlushed += $count;

            if (!$tab) {
                echo 'Total flushed: '.$totalFlushed.' documents.'.PHP_EOL;
            }

            $docs = [];
        }
    };

    $addDoc = function (array $data) use ($flush, &$docs, $bulkSize) {
        $docs[] = new Document('', $data);

        if (count($docs) % $bulkSize === 0) {
            $flush();
        }
    };

    $content = file_get_contents($file);

    $decoded = json_decode($content, true);

    $start = microtime(true);

    foreach ($decoded['features'] as $feature) {        
        $addDoc(
            [
                'code' => $feature['properties']['code'],
                'name' => $feature['properties']['nom'],
                'geoJson' => $feature['geometry'],
            ]
        );
    }   

    $flush();

    $end = microtime(true) - $start;
    
    $index->flush(true);
    
    sleep(1);
    
    $indexSize = $index->getStats()->getData()['indices'][$indexName]['primaries']['store']['size_in_bytes'] / 1024 / 1024;

    if (!$tab) {
        echo sprintf(
            'Done importing %d documents in %.4f seconds. Index size is %.2fMb',
            $totalFlushed,
            $end,
            $indexSize
        ).PHP_EOL;
    } else {
        echo implode(
            "\t",
            [
                $host,
                $port,
                $indexName,
                $typeName,
                $tree,
                $treeLevels,
                $precision,
                round($end, 4),
                $totalFlushed,
                $bulkSize,
                $indexSize
            ]
        ).PHP_EOL;
    }
};

$main();
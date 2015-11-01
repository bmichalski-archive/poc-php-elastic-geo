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
    $index = $argvInput->getOption('index');
    $type = $argvInput->getOption('type');
    $treeLevels = $argvInput->getOption('treeLevels');
    $precision = $argvInput->getOption('precision');
    $tree = $argvInput->getArgument('tree');
    
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

    echo sprintf(
        'Starting test on host %s:%s, using index %s and type %s, with tree %s using %s.',
        $host,
        $port,
        $index,
        $type,
        $tree,
        isset($treeLevels) ? 'treeLevels '.$treeLevels : 'precision '.$precision
    ).PHP_EOL;
    
    $elasticaClient = new Client(array(
        'host' => $host,
        'port' => $port
    ));

    $index = $elasticaClient->getIndex($index);
    
    $type = $index->getType($type);
    
    $doImport = function () use ($tree, $treeLevels, $precision, $index, $type) {
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

        $flush = function () use ($type, &$docs, &$totalFlushed, $index) {
            $count = count($docs);
            
            echo 'Flushing '.$count.' documents.'.PHP_EOL;
            
            $type->addDocuments($docs);

            $totalFlushed += $count;

            echo 'Total flushed: '.$totalFlushed.' documents.'.PHP_EOL;

            $docs = [];
        };

        $addDoc = function (array $data) use ($flush, &$docs) {
            $docs[] = new Document('', $data);

            if (count($docs) % 1000 === 0) {
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
        
        echo sprintf(
            'Done importing in %.4f seconds.',
            $end
        ).PHP_EOL;
    };
    
    $doImport();
};

$main();
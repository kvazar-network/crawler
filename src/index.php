<?php

// Load dependencies
require_once __DIR__ . '/../vendor/autoload.php';

// Init config
$config = json_decode(
    file_get_contents(
        __DIR__ . '/../config.json'
    )
);

// Init index
$index = new \Kvazar\Index\Manticore(
    (string) $config->index->name,
    (array)  $config->index->meta,
    (string) $config->index->host,
    (int)    $config->index->port
);
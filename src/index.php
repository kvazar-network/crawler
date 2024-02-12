<?php

// Prevent multi-thread execution
$semaphore = sem_get(
    crc32(__DIR__), 1
);

if (false === sem_acquire($semaphore, true))
{
    exit(
        _('Process locked by another thread!')
    );
}

// Load dependencies
require_once __DIR__ . '/../vendor/autoload.php';

// Init config
if (!file_exists(__DIR__ . '/../config.json'))
{
    exit(
        _('Config not found!')
    );
}

$config = json_decode(
    file_get_contents(
        __DIR__ . '/../config.json'
    )
);

// Init index
try
{
    $index = new \Kvazar\Index\Manticore(
        (string) $config->manticore->name,
        (array)  $config->manticore->meta,
        (string) $config->manticore->host,
        (int)    $config->manticore->port
    );
}

catch (Exception $exception)
{
    exit(
        print_r(
            $exception,
            true
        )
    );
}

// Init kevacoin
try
{
    $kevacoin = new \Kevachat\Kevacoin\Client(
        (string) $config->kevacoin->protocol,
        (string) $config->kevacoin->host,
        (int)    $config->kevacoin->port,
        (string) $config->kevacoin->username,
        (string) $config->kevacoin->password
    );
}

catch (Exception $exception)
{
    exit(
        print_r(
            $exception,
            true
        )
    );
}

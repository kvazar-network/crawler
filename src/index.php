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

// Load dependencies
require_once __DIR__ . '/../vendor/autoload.php';

// Init index
switch ($config->index->driver)
{
    case 'manticore':

        try
        {
            $index = new \Kvazar\Index\Manticore(
                (string) $config->index->manticore->name,
                (array)  $config->index->manticore->meta,
                (string) $config->index->manticore->host,
                (int)    $config->index->manticore->port
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

    break;

    default:

        exit(
            _('Undefined index driver!')
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

// Init optional commands
if (isset($argv[1]))
{
    switch ($argv[1])
    {
        // Drop index request
        case 'drop':

            $index->drop(
                true
            );

            exit(
                _('Index dropped!')
            );

        break;
    }
}

// Begin crawler

// @TODO
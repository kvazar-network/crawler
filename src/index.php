<?php

// Prevent multi-thread execution
$semaphore = sem_get(
    crc32(__FILE__), 1
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

// Init current block state
$state = 0;

if (file_exists(__DIR__ . '/../.state'))
{
    $state = (int) file_get_contents(
        __DIR__ . '/../.state'
    );
}

else
{
    file_put_contents(
        __DIR__ . '/../.state',
        $state
    );
}

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

            file_put_contents(
                __DIR__ . '/../.state',
                0
            );

            exit(
                _('Index dropped!')
            );

        break;

        // Index optimization request
        case 'optimize':

            $index->optimize();

            exit(
                _('Index optimized!')
            );

        break;
    }
}

// Begin crawler
if (false === $blocks = $kevacoin->getBlockCount())
{
    exit(
        _('Could not receive blocks count!')
    );
}

// Begin block index
for ($block = $state + 1; $block <= $blocks; $block++)
{
    // Debug progress
    echo sprintf(
        "%d/%d\r",
        $block,
        $blocks
    );

    // Get block hash
    if (!$hash = $kevacoin->getBlockHash($block))
    {
        exit(
            sprintf(
                _('Could not receive "%d" block hash!'),
                $block
            )
        );
    }

    // Get block data
    if (!$data = $kevacoin->getBlock($hash))
    {
        exit(
            sprintf(
                _('Could not receive "%d" block data by hash "%s"!'),
                $block,
                $hash
            )
        );
    }

    if (!isset($data['tx']))
    {
        exit(
            sprintf(
                _('Could not receive tx data in block "%d"!'),
                $transaction,
                $block
            )
        );
    }

    // Reset memory pool of processed transactions for each new block
    $transactions = [];

    // Process each transaction in block
    foreach ((array) $data['tx'] as $transaction)
    {
        // Validate each transaction
        if (!$raw = $kevacoin->getRawTransaction($transaction))
        {
            exit(
                sprintf(
                    _('Could not receive raw transaction "%s" in block "%d"!'),
                    $transaction,
                    $block
                )
            );
        }

        if (empty($raw['txid']))
        {
            exit(
                sprintf(
                    _('Could not receive txid of transaction "%s"  in block "%d"!'),
                    $transaction,
                    $block
                )
            );
        }

        if (!isset($raw['vout']))
        {
            exit(
                sprintf(
                    _('Could not receive vout of transaction "%s" in block "%d"!'),
                    $transaction,
                    $block
                )
            );
        }

        if (empty($raw['time']))
        {
            exit(
                sprintf(
                    _('Could not receive time of transaction "%s"  in block "%d"!'),
                    $transaction,
                    $block
                )
            );
        }

        if (empty($raw['size']))
        {
            exit(
                sprintf(
                    _('Could not receive size of transaction "%s"  in block "%d"!'),
                    $transaction,
                    $block
                )
            );
        }

        // Parse transaction data
        foreach((array) $raw['vout'] as $vout) {

            if (!$vout || empty($vout['scriptPubKey']) || empty($vout['scriptPubKey']['asm']))
            {
                exit(
                    sprintf(
                        _('Invalid vout transaction "%s" in block "%d"!'),
                        $transaction,
                        $block
                    )
                );
            }

            // Parse space-separated fragments to array
            $asm = explode(
                ' ',
                $vout['scriptPubKey']['asm']
            );

            // Operation ID required to continue
            if (empty($asm[0]))
            {
                continue;
            }

            // Detect key / value
            switch ($asm[0]) {

                case 'OP_KEVA_PUT':
                case 'OP_KEVA_NAMESPACE':

                    // Namespace info required to continue
                    if (empty($asm[1]))
                    {
                        exit(
                            sprintf(
                                _('Undefined namespace of transaction "%s" in block "%d"!'),
                                $transaction,
                                $block
                            )
                        );
                    }

                    // Decode namespace
                    $namespace = \Kvazar\Crypto\Base58::encode(
                        $asm[1],
                        false,
                        0,
                        false
                    );

                    // Find natively decoded data in block by namespace
                    // https://github.com/kvazar-network/crawler/issues/1
                    // @TODO complete \Kvazar\Crypto\Kevacoin to decode tx faster
                    foreach ((array) $kevacoin->kevaFilter(
                        $namespace
                    ) as $record)
                    {
                        if (
                            // Filter current block only
                            $record['height'] === $block
                            &&
                            // Skip processed transaction
                            !in_array($record['txid'], $transactions)
                        ) {
                            // Register new transaction
                            $index->add(
                                $raw['time'],
                                $raw['size'],
                                $block,
                                $namespace,
                                $record['txid'],
                                $asm[0],
                                $record['key'],
                                $record['value']
                            );

                            // Register processed transaction
                            $transactions[] = $record['txid'];
                        }
                    }

                    // Find namespace alias (not always return by kevaFilter)
                    if ($record = $kevacoin->kevaGet($namespace, '_KEVA_NS_'))
                    {
                        if (
                            // Filter current block only
                            $record['height'] === $block
                            &&
                            // Skip processed transaction
                            !in_array($record['txid'], $transactions)
                        ) {
                            // Register new transaction
                            $index->add(
                                $raw['time'],
                                $raw['size'],
                                $block,
                                $namespace,
                                $record['txid'],
                                $asm[0],
                                $record['key'],
                                $record['value']
                            );

                            // Register processed transaction
                            $transactions[] = $record['txid'];
                        }
                    }

                break;

                // @TODO not in use at this moment
                case 'OP_KEVA_DELETE':
                case 'OP_HASH160':
                case 'OP_RETURN':
                case 'OP_DUP':
                case 'OP_NOP':

                break;

                default:

                    exit(
                        sprintf(
                            _('Unknown operation "%s" of transaction "%s" in block "%d"!'),
                            $asm[0],
                            $transaction,
                            $block
                        )
                    );
            }
        }
    }

    // Update current block state
    file_put_contents(
        __DIR__ . '/../.state',
        $block
    );
}
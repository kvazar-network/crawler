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
$state = 1;

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
                1
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

for ($block = $state; $block <= $blocks; $block++)
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

                    if (empty($asm[1]) || empty($asm[2]) || empty($asm[3]))
                    {
                        continue 2;
                    }

                    $namespace = \Kvazar\Crypto\Base58::encode(
                        $asm[1], false, 0, false
                    );

                    $key = \Kvazar\Crypto\Kevacoin::decode(
                        $asm[2]
                    );

                    $value = \Kvazar\Crypto\Kevacoin::decode(
                        $asm[3]
                    );

                break;

                case 'OP_KEVA_NAMESPACE':

                    if (empty($asm[1]) || empty($asm[2]))
                    {
                        continue 2;
                    }

                    $namespace = \Kvazar\Crypto\Base58::encode(
                        $asm[1], false, 0, false
                    );

                    $key = '_KEVA_NS_';

                    $value = \Kvazar\Crypto\Kevacoin::decode(
                        $asm[2]
                    );

                break;

                // @TODO not in use at this moment
                case 'OP_KEVA_DELETE':
                case 'OP_HASH160':
                case 'OP_RETURN':
                case 'OP_DUP':
                case 'OP_NOP':

                    continue 2;

                break;

                default:

                    exit(
                        sprintf(
                            _('Undefined operation "%s" of transaction "%s" in block "%d"!'),
                            $asm[0],
                            $transaction,
                            $block
                        )
                    );
            }

            // Skip binary index
            if (false === mb_detect_encoding((string) $namespace, null, true)
                ||
                false === mb_detect_encoding((string) $key, null, true)
                ||
                false === mb_detect_encoding((string) $value, null, true))
            {
                continue;
            }

            // Skip base64 index
            if (base64_encode(base64_decode($namespace, true)) === $namespace
                ||
                base64_encode(base64_decode($key, true)) === $key
                ||
                base64_encode(base64_decode($value, true)) === $value)
            {
                continue;
            }

            // Add index record
            $index->add(
                $raw['time'],
                $raw['size'],
                $block,
                $namespace,
                $raw['txid'],
                $asm[0],
                $key,
                $value
            );
        }
    }

    // Update current block state
    file_put_contents(
        __DIR__ . '/../.state',
        $block + 1
    );
}
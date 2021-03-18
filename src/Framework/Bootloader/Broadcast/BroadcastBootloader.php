<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Spiral\Bootloader\Broadcast;

use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\Exception\BootException;
use Spiral\Broadcast\Broadcast;
use Spiral\Broadcast\BroadcastInterface;

/**
 * Enables support for event/message publishing.
 */
final class BroadcastBootloader extends Bootloader
{
    protected const SINGLETONS = [
        BroadcastInterface::class => Broadcast::class,
        Broadcast::class          => Broadcast::class,
    ];

    /**
     * BroadcastBootloader constructor.
     */
    public function __construct()
    {
        if (! \interface_exists(BroadcastInterface::class)) {
            throw new BootException(
                'Unable to find [spiral/broadcast] dependency, ' .
                'please install it using Composer:' . \PHP_EOL .
                '    composer require spiral/broadcast'
            );
        }
    }
}

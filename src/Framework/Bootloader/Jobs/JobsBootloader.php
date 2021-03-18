<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Spiral\Bootloader\Jobs;

use Psr\Container\ContainerInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\Exception\BootException;
use Spiral\Boot\KernelInterface;
use Spiral\Bootloader\ServerBootloader;
use Spiral\Core\Container;
use Spiral\Goridge\RPC as LegacyRPC;
use Spiral\Goridge\RPC\RPC;
use Spiral\GRPC\ServiceInterface;
use Spiral\Jobs\HandlerRegistryInterface;
use Spiral\Jobs\JobDispatcher;
use Spiral\Jobs\JobQueue;
use Spiral\Jobs\JobRegistry;
use Spiral\Jobs\QueueInterface;
use Spiral\Jobs\Registry\ContainerRegistry;
use Spiral\Jobs\SerializerRegistryInterface;

final class JobsBootloader extends Bootloader
{
    protected const DEPENDENCIES = [
        ServerBootloader::class,
    ];

    protected const SINGLETONS = [
        HandlerRegistryInterface::class    => JobRegistry::class,
        SerializerRegistryInterface::class => JobRegistry::class,
        JobRegistry::class                 => [self::class, 'jobRegistry'],
    ];

    /**
     * JobsBootloader constructor.
     */
    public function __construct()
    {
        if (! \interface_exists(QueueInterface::class)) {
            throw new BootException(
                'Unable to find [spiral/jobs] dependency, ' .
                'please install it using Composer:' . \PHP_EOL .
                '    composer require spiral/jobs'
            );
        }
    }

    /**
     * @param Container $container
     * @param KernelInterface $kernel
     * @param JobDispatcher $jobs
     */
    public function boot(Container $container, KernelInterface $kernel, JobDispatcher $jobs): void
    {
        $kernel->addDispatcher($jobs);

        if (\class_exists(LegacyRPC::class)) {
            $container->bindSingleton(QueueInterface::class, function (LegacyRPC $rpc, JobRegistry $registry) {
                return new JobQueue($rpc, $registry);
            });
        } else {
            $container->bindSingleton(QueueInterface::class, function (RPC $rpc, JobRegistry $registry) {
                return new JobQueue($rpc, $registry);
            });
        }
    }

    /**
     * @param ContainerInterface $container
     * @param ContainerRegistry $registry
     * @return JobRegistry
     */
    private function jobRegistry(ContainerInterface $container, ContainerRegistry $registry)
    {
        return new JobRegistry($container, $registry, $registry);
    }
}

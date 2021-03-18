<?php

/**
 * This file is part of Spiral Framework package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\Bootloader\Server;

use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\EnvironmentInterface as GlobalEnvironmentInterface;
use Spiral\Core\Container;
use Spiral\Goridge\RPC\RPC;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\Http\Diactoros\ServerRequestFactory;
use Spiral\Http\Diactoros\StreamFactory;
use Spiral\Http\Diactoros\UploadedFileFactory;
use Spiral\RoadRunner\Environment;
use Spiral\RoadRunner\EnvironmentInterface;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Http\PSR7WorkerInterface;
use Spiral\RoadRunner\Worker;
use Spiral\RoadRunner\WorkerInterface;

class RoadRunnerBootloader extends Bootloader
{
    /**
     * @param Container $container
     */
    public function boot(Container $container)
    {
        //
        // Register RoadRunner Environment
        //
        $container->bindSingleton(
            EnvironmentInterface::class,
            static function (GlobalEnvironmentInterface $env): EnvironmentInterface {
                return new Environment($env->getAll());
            }
        );

        $container->bindSingleton(
            Environment::class,
            static function (EnvironmentInterface $env): EnvironmentInterface {
                return $env;
            }
        );

        //
        // Register RPC
        //
        $container->bindSingleton(
            RPCInterface::class,
            static function (EnvironmentInterface $env): RPCInterface {
                return RPC::create($env->getRPCAddress());
            }
        );

        $container->bindSingleton(
            RPC::class,
            static function (RPCInterface $rpc): RPCInterface {
                return $rpc;
            }
        );

        //
        // Register Worker
        //
        $container->bindSingleton(
            WorkerInterface::class,
            static function (EnvironmentInterface $env): WorkerInterface {
                return Worker::createFromEnvironment($env);
            }
        );

        $container->bindSingleton(
            Worker::class,
            static function (WorkerInterface $worker): WorkerInterface {
                return $worker;
            }
        );

        //
        // Register PSR Worker
        //
        $container->bindSingleton(
            PSR7WorkerInterface::class,
            static function (
                WorkerInterface $worker,
                ServerRequestFactory $requests,
                StreamFactory $streams,
                UploadedFileFactory $uploads
            ): PSR7WorkerInterface {
                return new PSR7Worker($worker, $requests, $streams, $uploads);
            }
        );

        $container->bindSingleton(
            PSR7Worker::class,
            static function (PSR7WorkerInterface $psr7): PSR7WorkerInterface {
                return $psr7;
            }
        );
    }
}

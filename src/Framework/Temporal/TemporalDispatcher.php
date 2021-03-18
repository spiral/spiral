<?php

/**
 * This file is part of Spiral Framework package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\Temporal;

use Spiral\Boot\DispatcherInterface;
use Spiral\Boot\FinalizerInterface;
use Spiral\RoadRunner\Environment\Mode;
use Spiral\RoadRunner\EnvironmentInterface;
use Temporal\Worker\WorkerFactoryInterface;
use Temporal\WorkerFactory;

final class TemporalDispatcher implements DispatcherInterface
{
    /**
     * @var WorkerFactoryInterface
     */
    private $temporal;

    /**
     * @var EnvironmentInterface
     */
    private $env;
    /**
     * @var FinalizerInterface
     */
    private $finalizer;

    /**
     * @param WorkerFactoryInterface $temporal
     * @param EnvironmentInterface $env
     * @param FinalizerInterface $f
     */
    public function __construct(WorkerFactoryInterface $temporal, EnvironmentInterface $env, FinalizerInterface $f)
    {
        $this->temporal = $temporal;
        $this->env = $env;
        $this->finalizer = $f;
    }

    /**
     * @return bool
     */
    public function canServe(): bool
    {
        return $this->env->getMode() === Mode::MODE_TEMPORAL;
    }

    /**
     * @return mixed
     */
    public function serve()
    {
        $this->registerTickHandler();

        return $this->temporal->run();
    }

    /**
     * @return void
     */
    private function registerTickHandler(): void
    {
        if ($this->temporal instanceof WorkerFactory) {
            $this->temporal->once(WorkerFactory::ON_TICK, function () {
                $this->finalizer->finalize(false);
                $this->registerTickHandler();
            });
        }
    }
}

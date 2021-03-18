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
use Spiral\RoadRunner\Environment\Mode;
use Spiral\RoadRunner\EnvironmentInterface;
use Temporal\Worker\WorkerFactoryInterface;

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
     * @param WorkerFactoryInterface $temporal
     * @param EnvironmentInterface $env
     */
    public function __construct(WorkerFactoryInterface $temporal, EnvironmentInterface $env)
    {
        $this->temporal = $temporal;
        $this->env = $env;
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
        return $this->temporal->run();
    }
}

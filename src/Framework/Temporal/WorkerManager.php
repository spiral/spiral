<?php

/**
 * This file is part of Spiral Framework package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\Temporal;

use Temporal\Worker\WorkerFactoryInterface;

final class WorkerManager extends Manager
{
    /**
     * @var WorkerFactoryInterface
     */
    private $factory;

    /**
     * @param WorkerFactoryInterface $factory
     */
    public function __construct(WorkerFactoryInterface $factory)
    {
        $this->factory = $factory;
    }
}

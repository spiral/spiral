<?php

/**
 * This file is part of Spiral Framework package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\Temporal;

use Spiral\RoadRunner\WorkerInterface;

interface WorkerManagerInterface
{
    /**
     * @param string|null $worker
     * @return WorkerInterface
     */
    public function get(string $worker = null): WorkerInterface;
}

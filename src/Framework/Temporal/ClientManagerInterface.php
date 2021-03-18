<?php

/**
 * This file is part of Spiral Framework package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\Temporal;

use Temporal\Client\WorkflowClientInterface;

interface ClientManagerInterface
{
    /**
     * @param string|null $worker
     * @return WorkflowClientInterface
     */
    public function get(string $worker = null): WorkflowClientInterface;
}

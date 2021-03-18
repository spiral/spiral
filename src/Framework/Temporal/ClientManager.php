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

/**
 * @template-extends Manager<WorkflowClientInterface>
 */
final class ClientManager extends Manager implements ClientManagerInterface
{
    public function get(string $worker = null): WorkflowClientInterface
    {
        $client = $this->resolve($worker);

        return $client;
    }
}

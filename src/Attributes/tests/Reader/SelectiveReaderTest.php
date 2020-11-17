<?php

/**
 * This file is part of Spiral Framework package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\Tests\Attributes\Reader;

use Spiral\Attributes\AnnotationReader;
use Spiral\Attributes\Composite\SelectiveReader;
use Spiral\Attributes\NativeAttributeReader;
use Spiral\Attributes\ReaderInterface;

/**
 * @requires PHP >= 8.0
 */
class SelectiveReaderTest extends ReaderTestCase
{
    protected function getReader(): ReaderInterface
    {
        return new SelectiveReader([
            new NativeAttributeReader(),
            new AnnotationReader()
        ]);
    }
}
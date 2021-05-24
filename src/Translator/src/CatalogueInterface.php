<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Spiral\Translator;

use Spiral\Translator\Exception\CatalogueException;

interface CatalogueInterface
{
    /**
     * @return string
     */
    public function getLocale(): string;

    /**
     * All domains registered within catalogue.
     *
     * @return array
     */
    public function getDomains(): array;

    /**
     * Check if domain message exists.
     *
     * @param string $domain
     * @param string $id
     * @return bool
     */
    public function has(string $domain, string $id): bool;

    /**
     * Get message from the catalogue.
     *
     * @param string $domain
     * @param string $id
     * @return string
     *
     * @throws CatalogueException
     */
    public function get(string $domain, string $id): string;

    /**
     * Set/replace translation in catalogue.
     *
     * @param string $domain
     * @param string $id
     * @param string $translation
     */
    public function set(string $domain, string $id, string $translation);

    /**
     * Must return all locale data.
     *
     * @return array
     */
    public function getData(): array;
}

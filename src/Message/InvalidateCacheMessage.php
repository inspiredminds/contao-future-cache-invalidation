<?php

declare(strict_types=1);

/*
 * (c) INSPIRED MINDS
 */

namespace InspiredMinds\ContaoFutureCacheInvalidation\Message;

class InvalidateCacheMessage
{
    /**
     * @param list<string> $paths
     * @param list<string> $regex
     * @param list<string> $tags
     */
    public function __construct(
        private readonly array $paths = [],
        private readonly array $regex = [],
        private readonly array $tags = [],
    ) {
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function getPaths(): array
    {
        return $this->paths;
    }

    public function getRegex(): array
    {
        return $this->regex;
    }
}

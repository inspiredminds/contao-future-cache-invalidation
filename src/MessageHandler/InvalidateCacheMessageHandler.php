<?php

declare(strict_types=1);

/*
 * (c) INSPIRED MINDS
 */

namespace InspiredMinds\ContaoFutureCacheInvalidation\MessageHandler;

use FOS\HttpCacheBundle\CacheManager;
use InspiredMinds\ContaoFutureCacheInvalidation\Message\InvalidateCacheMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class InvalidateCacheMessageHandler
{
    public function __construct(private readonly CacheManager $cacheManager)
    {
    }

    public function __invoke(InvalidateCacheMessage $invalidateCacheMessage): void
    {
        foreach ($invalidateCacheMessage->getPaths() as $path) {
            $this->cacheManager->invalidatePath($path);
        }

        foreach ($invalidateCacheMessage->getRegex() as $regex) {
            $this->cacheManager->invalidateRegex($regex);
        }

        if ($tags = $invalidateCacheMessage->getTags()) {
            $this->cacheManager->invalidateTags($tags);
        }

        $this->cacheManager->flush();
    }
}

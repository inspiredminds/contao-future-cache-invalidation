<?php

declare(strict_types=1);

/*
 * (c) INSPIRED MINDS
 */

namespace InspiredMinds\ContaoFutureCacheInvalidation\MessageHandler;

use FOS\HttpCache\Exception\UnsupportedProxyOperationException;
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
        try {
            foreach ($invalidateCacheMessage->getPaths() as $path) {
                $this->cacheManager->invalidatePath($path);
            }

            foreach ($invalidateCacheMessage->getRegex() as $regex) {
                $this->cacheManager->invalidateRegex($regex);
            }

            if ($tags = $invalidateCacheMessage->getTags()) {
                $this->cacheManager->invalidateTags($tags);
            }

            if ($invalidateCacheMessage->getClear()) {
                if ($this->cacheManager->supports(CacheManager::CLEAR)) {
                    $this->cacheManager->clearCache();
                } elseif ($this->cacheManager->supports(CacheManager::INVALIDATE)) {
                    $this->cacheManager->invalidateRegex('.*');
                }
            }
        } catch (UnsupportedProxyOperationException) {
            // noop
        } finally {
            $this->cacheManager->flush();
        }
    }
}

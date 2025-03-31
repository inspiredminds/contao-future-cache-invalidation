<?php

declare(strict_types=1);

/*
 * (c) INSPIRED MINDS
 */

namespace InspiredMinds\ContaoFutureCacheInvalidation\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;

#[AsHook('loadDataContainer')]
class LoadDataContainerListener
{
    public function __invoke(string $table): void
    {
        $dca = &$GLOBALS['TL_DCA'][$table];

        foreach (['start', 'stop'] as $field) {
            if (!($dca['fields'][$field] ?? null)) {
                continue;
            }

            if (!\is_array($dca['fields'][$field]['save_callback'] ?? null)) {
                $dca['fields'][$field]['save_callback'] = [];
            }

            foreach ($dca['fields'][$field]['save_callback'] as $callback) {
                if (\is_array($callback) && SaveCallbackListener::class === $callback[0]) {
                    continue 2;
                }
            }

            $dca['fields'][$field]['save_callback'][] = [SaveCallbackListener::class, \sprintf('onSave%s', ucfirst($field))];
        }
    }
}

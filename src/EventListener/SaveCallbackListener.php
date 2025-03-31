<?php

declare(strict_types=1);

/*
 * (c) INSPIRED MINDS
 */

namespace InspiredMinds\ContaoFutureCacheInvalidation\EventListener;

use Contao\DataContainer;
use InspiredMinds\ContaoFutureCacheInvalidation\Message\InvalidateCacheMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

class SaveCallbackListener
{
    public function __construct(private readonly MessageBusInterface $messageBus)
    {
    }

    public function onSaveStart(mixed $value, DataContainer $dc): mixed
    {
        $this->dispatchMessage($value, $dc, true);

        return $value;
    }

    public function onSaveStop(mixed $value, DataContainer $dc): mixed
    {
        $this->dispatchMessage($value, $dc);

        return $value;
    }

    private function dispatchMessage(mixed $value, DataContainer $dc, bool $isStart = false): void
    {
        if (!$dc->id || !$value) {
            return;
        }

        if (($delay = (int) $value - time()) <= 0) {
            return;
        }

        $tags = [\sprintf('contao.db.%s.%s', $dc->table, $dc->id)];
        $clear = false;

        if ($isStart) {
            // If this is the "start" date, then we want to invalidate the parent's tag
            // (since there won't be a tag for the current record). If no parent is present
            // (like for tl_page) we invalidate the whole cache instead.
            if ($dc->activeRecord->pid && ($ptable = $this->getPtable($dc))) {
                $tags[] = \sprintf('contao.db.%s.%s', $ptable, $dc->activeRecord->pid);
            } else {
                $clear = true;
            }
        }

        $this->messageBus->dispatch(new InvalidateCacheMessage(tags: $tags, clear: $clear), [new DelayStamp($delay * 1000)]);
    }

    private function getPtable(DataContainer $dc): string|null
    {
        $ptable = $GLOBALS['TL_DCA'][$dc->table]['config']['ptable'] ?? null;
        $dynamicPtable = $GLOBALS['TL_DCA'][$dc->table]['config']['dynamicPtable'] ?? false;

        if (!$ptable && !$dynamicPtable) {
            return null;
        }

        if ($dynamicPtable && $dc->activeRecord->ptable) {
            return $dc->activeRecord->ptable ?: 'tl_article';
        }

        return $ptable;
    }
}

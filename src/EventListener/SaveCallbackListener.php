<?php

declare(strict_types=1);

/*
 * (c) INSPIRED MINDS
 */

namespace InspiredMinds\ContaoFutureCacheInvalidation\EventListener;

use Contao\Controller;
use Contao\DataContainer;
use Contao\DC_Table;
use Doctrine\DBAL\Connection;
use InspiredMinds\ContaoFutureCacheInvalidation\Message\InvalidateCacheMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

class SaveCallbackListener
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly Connection $db,
    ) {
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
        $activeRecord = $this->getActiveRecord($dc);

        if ($isStart) {
            // If this is the "start" date, then we want to invalidate the parent's tag
            // (since there won't be a tag for the current record). If no parent is present
            // (like for tl_page) we invalidate the whole cache instead.
            if (($activeRecord['pid'] ?? null) && ($ptable = $this->getPtable($dc->table, $activeRecord))) {
                $tags[] = \sprintf('contao.db.%s.%s', $ptable, $activeRecord['pid']);

                // If all content elements of an article have a start time set,
                // the article will not be rendered. So lets also invalidate
                // one level up, if available.
                $parent = $this->db->fetchAssociative('SELECT * FROM '.$this->db->quoteIdentifier($ptable).' WHERE id = ?', [$activeRecord['pid']]);

                if ($parent && ($parentPtable = $this->getPtable($ptable, $parent))) {
                    $tags[] = \sprintf('contao.db.%s.%s', $parentPtable, $parent['pid']);
                }
            } else {
                $clear = true;
            }
        }

        $this->messageBus->dispatch(new InvalidateCacheMessage(tags: $tags, clear: $clear), [new DelayStamp($delay * 1000)]);
    }

    private function getPtable(string $table, array|null $record = null): string|null
    {
        Controller::loadDataContainer($table);

        $ptable = $GLOBALS['TL_DCA'][$table]['config']['ptable'] ?? null;
        $dynamicPtable = $GLOBALS['TL_DCA'][$table]['config']['dynamicPtable'] ?? false;

        if (!$ptable && !$dynamicPtable) {
            return null;
        }

        if ($dynamicPtable && isset($record['ptable'])) {
            return $record['ptable'] ?: 'tl_article';
        }

        return $ptable;
    }

    private function getActiveRecord(DataContainer $dc): array
    {
        if (!$dc instanceof DC_Table && method_exists($dc, 'getCurrentRecord')) {
            return $dc->getCurrentRecord();
        }

        if (method_exists($dc, 'getActiveRecord')) {
            return $dc->getActiveRecord();
        }

        return (array) $dc->activeRecord;
    }
}

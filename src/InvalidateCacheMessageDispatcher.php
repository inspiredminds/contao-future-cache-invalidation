<?php

declare(strict_types=1);

/*
 * (c) INSPIRED MINDS
 */

namespace InspiredMinds\ContaoFutureCacheInvalidation;

use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Doctrine\DBAL\Connection;
use InspiredMinds\ContaoFutureCacheInvalidation\Message\InvalidateCacheMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

class InvalidateCacheMessageDispatcher
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly Connection $db,
        private readonly ContaoFramework $contaoFramework,
    ) {
    }

    public function dispatchMessageForRecord(string $table, array $record): void
    {
        $this->contaoFramework->initialize();

        foreach (['start', 'stop'] as $field) {
            if (!($record['id'] ?? null)) {
                continue;
            }

            $value = $record[$field] ?? '';

            if ('' === $value) {
                continue;
            }

            if (($delay = (int) $value - time()) <= 0) {
                continue;
            }

            $tags = [\sprintf('contao.db.%s.%s', $table, $record['id'])];

            if ('start' === $field) {
                // If this is the "start" date, then we want to invalidate the parent's tag
                // (since there won't be a tag for the current record). If no parent is present
                // we invalidate "contao.db.tl_example" instead.
                if (($record['pid'] ?? null) && ($ptable = $this->getPtable($table, $record))) {
                    $tags[] = \sprintf('contao.db.%s.%s', $ptable, $record['pid']);

                    // If all content elements of an article have a start time set,
                    // the article will not be rendered. So lets also invalidate
                    // one level up, if available.
                    $parent = $this->db->fetchAssociative('SELECT * FROM '.$this->db->quoteIdentifier($ptable).' WHERE id = ?', [$record['pid']]);

                    if ($parent && ($parentPtable = $this->getPtable($ptable, $parent))) {
                        $tags[] = \sprintf('contao.db.%s.%s', $parentPtable, $parent['pid']);
                    }
                } else {
                    $tags[] = \sprintf('contao.db.%s', $table);
                }
            }

            $this->messageBus->dispatch(new InvalidateCacheMessage(tags: $tags), [new DelayStamp($delay * 1000)]);
        }
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
}

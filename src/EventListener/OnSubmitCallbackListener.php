<?php

declare(strict_types=1);

/*
 * (c) INSPIRED MINDS
 */

namespace InspiredMinds\ContaoFutureCacheInvalidation\EventListener;

use Contao\Database\Result;
use Contao\DataContainer;
use Contao\DC_Table;
use Contao\Model;
use InspiredMinds\ContaoFutureCacheInvalidation\InvalidateCacheMessageDispatcher;

class OnSubmitCallbackListener
{
    public function __construct(
        private readonly InvalidateCacheMessageDispatcher $invalidateCacheMessageDispatcher,
    ) {
    }

    public function __invoke(DataContainer $dc): void
    {
        $this->invalidateCacheMessageDispatcher->dispatchMessageForRecord($dc->table, $this->getActiveRecord($dc));
    }

    private function getActiveRecord(DataContainer $dc): array
    {
        if (!$dc instanceof DC_Table && method_exists($dc, 'getCurrentRecord')) {
            return $dc->getCurrentRecord();
        }

        if (method_exists($dc, 'getActiveRecord')) {
            return $dc->getActiveRecord();
        }

        $activeRecord = $dc->activeRecord;

        if ($activeRecord instanceof Result || $activeRecord instanceof Model) {
            return $activeRecord->row();
        }

        return (array) $activeRecord;
    }
}

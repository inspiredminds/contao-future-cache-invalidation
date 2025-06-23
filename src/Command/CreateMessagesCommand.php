<?php

declare(strict_types=1);

/*
 * (c) INSPIRED MINDS
 */

namespace InspiredMinds\ContaoFutureCacheInvalidation\Command;

use Contao\Controller;
use Contao\CoreBundle\Config\ResourceFinderInterface;
use Contao\CoreBundle\Framework\ContaoFramework;
use Doctrine\DBAL\Connection;
use InspiredMinds\ContaoFutureCacheInvalidation\InvalidateCacheMessageDispatcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('contao_future_cache_invalidation:create-messages', 'Creates messages for all DataContainers where start and stop fields are present and set in the database.')]
class CreateMessagesCommand extends Command
{
    public function __construct(
        private readonly ResourceFinderInterface $resourceFinder,
        private readonly Connection $db,
        private readonly ContaoFramework $contaoFramework,
        private readonly InvalidateCacheMessageDispatcher $invalidateCacheMessageDispatcher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->contaoFramework->initialize();

        $schemaManager = $this->db->createSchemaManager();

        $dataContainers = $this->resourceFinder->findIn('dca')->depth(0)->files()->name('*.php');
        $processed = [];

        foreach ($dataContainers as $file) {
            $table = $file->getBasename('.php');

            if (\in_array($table, $processed, true)) {
                continue;
            }

            $processed[] = $table;

            if (!$schemaManager->tablesExist([$table])) {
                continue;
            }

            Controller::loadDataContainer($table);

            $fields = &$GLOBALS['TL_DCA'][$table]['fields'];

            if (!isset($fields['start'], $fields['stop'])) {
                continue;
            }

            $records = $this->db->fetchAllAssociative(\sprintf("SELECT * FROM %s WHERE (start != '' AND start >= UNIX_TIMESTAMP()) OR (stop != '' AND stop >= UNIX_TIMESTAMP())", $this->db->quoteIdentifier($table)));

            foreach ($records as $record) {
                $this->invalidateCacheMessageDispatcher->dispatchMessageForRecord($table, $record);
            }
        }

        return Command::SUCCESS;
    }
}

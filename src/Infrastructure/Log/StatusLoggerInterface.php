<?php
declare(strict_types=1);

namespace App\Infrastructure\Log;

use App\Domain\Collection\CollectionStrategyInterface;
use App\Domain\Status\StatusInterface;

interface StatusLoggerInterface
{
    public function logHowManyItemsHaveBeenCollected(
        CollectionStrategyInterface $collectionStrategy,
        int $totalStatuses,
        array $forms,
        int $lastCollectionBatchSize
    ): void;

    public function logHowManyItemsHaveBeenFetched(
        array $statuses,
        string $screenName
    ): void;

    public function logIntentionWithRegardsToAggregate(
        $options,
        CollectionStrategyInterface $collectionStrategy
    ): void;

    public function logStatus(StatusInterface $status): void;
}
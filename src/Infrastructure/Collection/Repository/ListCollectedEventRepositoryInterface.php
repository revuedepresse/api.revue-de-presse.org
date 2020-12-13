<?php

declare(strict_types=1);

namespace App\Infrastructure\Collection\Repository;

use App\Domain\Collection\Entity\ListCollectedEvent;
use App\Infrastructure\Twitter\Api\Accessor\ListAccessorInterface;
use App\Infrastructure\Twitter\Api\Resource\ResourceList;
use App\Infrastructure\Twitter\Api\Selector\ListSelector;

/**
 * @method ListCollectedEvent|null find($id, $lockMode = null, $lockVersion = null)
 * @method ListCollectedEvent|null findOneBy(array $criteria, array $orderBy = null)
 * @method ListCollectedEvent[]    findAll()
 * @method ListCollectedEvent[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
interface ListCollectedEventRepositoryInterface
{
    public function aggregatedLists(
        ListAccessorInterface $accessor,
        string $screenName
    ): ResourceList;

    public function collectedList(
        ListAccessorInterface $accessor,
        ListSelector $selector
    ): ResourceList;
}
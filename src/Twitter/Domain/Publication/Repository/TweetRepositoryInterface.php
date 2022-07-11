<?php
declare(strict_types=1);

namespace App\Twitter\Domain\Publication\Repository;

use App\Twitter\Domain\Publication\TweetInterface;
use App\Twitter\Infrastructure\Publication\Dto\TaggedTweet;
use App\Twitter\Infrastructure\Publication\Mapping\MappingAwareInterface;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Persistence\ObjectRepository;

interface TweetRepositoryInterface extends ObjectRepository, ExtremumAwareInterface
{
    public function findNextExtremum(
        string $screenName,
        string $direction = ExtremumAwareInterface::FINDING_IN_ASCENDING_ORDER,
        ?string $before = null
    ): array;

    public function reviseDocument(TaggedTweet $taggedTweet): TweetInterface;

    public function queryPublicationCollection(
        string $memberScreenName,
        DateTimeInterface $earliestDate,
        DateTimeInterface $latestDate
    );

    public function mapStatusCollectionToService(
        MappingAwareInterface $service,
        ArrayCollection $statuses
    ): iterable;
}
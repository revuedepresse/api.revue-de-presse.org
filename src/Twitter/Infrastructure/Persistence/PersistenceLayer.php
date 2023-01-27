<?php
declare(strict_types=1);

namespace App\Twitter\Infrastructure\Persistence;

use App\Membership\Domain\Repository\MemberRepositoryInterface;
use App\Membership\Infrastructure\DependencyInjection\MemberRepositoryTrait;
use App\Twitter\Domain\Operation\Collection\CollectionInterface;
use App\Twitter\Domain\Persistence\PersistenceLayerInterface;
use App\Twitter\Domain\Persistence\TweetPersistenceLayerInterface;
use App\Twitter\Domain\Publication\Repository\TweetPublicationPersistenceLayerInterface;
use App\Twitter\Domain\Publication\TweetInterface;
use App\Twitter\Infrastructure\Http\AccessToken\AccessToken;
use App\Twitter\Infrastructure\Http\Adapter\StatusToArray;
use App\Twitter\Infrastructure\DependencyInjection\Publication\PublicationRepositoryTrait;
use App\Twitter\Infrastructure\DependencyInjection\Persistence\TweetPersistenceLayerTrait;
use App\Twitter\Infrastructure\Operation\Collection\Collection;
use App\Twitter\Infrastructure\Publication\Entity\PublishersList;
use Doctrine\ORM\EntityManagerInterface;
use function count;

class PersistenceLayer implements PersistenceLayerInterface
{
    use MemberRepositoryTrait;
    use PublicationRepositoryTrait;
    use TweetPersistenceLayerTrait;

    private EntityManagerInterface $entityManager;

    public function __construct(
        TweetPersistenceLayerInterface            $statusPersistence,
        TweetPublicationPersistenceLayerInterface $publicationRepository,
        MemberRepositoryInterface                 $memberRepository,
        EntityManagerInterface                    $entityManager
    ) {
        $this->tweetPersistenceLayer = $statusPersistence;
        $this->publicationRepository = $publicationRepository;
        $this->entityManager = $entityManager;
        $this->memberRepository = $memberRepository;
    }

    public function persistTweetsCollection(
        array $statuses,
        AccessToken $identifier,
        PublishersList $twitterList = null
    ): CollectionInterface {
        $persistenceLayer = $this->tweetPersistenceLayer;
        $result           = $persistenceLayer->persistTweetsCollection(
            $statuses,
            $identifier,
            $twitterList
        );
        $normalizedTweetsCollection = $result[$persistenceLayer::PROPERTY_NORMALIZED_STATUS];
        $screenName       = $result[$persistenceLayer::PROPERTY_SCREEN_NAME];

        // Mark status as published
        $tweetCollection = new Collection(
            $result[$persistenceLayer::PROPERTY_STATUS]->toArray()
        );
        $tweetCollection->map(fn(TweetInterface $status) => $status->markAsPublished());

        // Make publications
        $tweetCollection = StatusToArray::fromStatusCollection($tweetCollection);
        $this->publicationRepository->persistTweetsCollection($tweetCollection);

        // Commit transaction
        $this->entityManager->flush();

        if (count($normalizedTweetsCollection) > 0) {
            $this->memberRepository->incrementTotalStatusesOfMemberWithName(
                count($normalizedTweetsCollection),
                $screenName
            );
        }

        return $normalizedTweetsCollection;
    }
}
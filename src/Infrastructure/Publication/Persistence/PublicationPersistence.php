<?php
declare(strict_types=1);

namespace App\Infrastructure\Publication\Persistence;

use App\Infrastructure\Api\AccessToken\AccessToken;
use App\Infrastructure\Api\Adapter\StatusToArray;
use App\Infrastructure\Api\Entity\Aggregate;
use App\Domain\Publication\StatusInterface;
use App\Infrastructure\DependencyInjection\Membership\MemberRepositoryTrait;
use App\Infrastructure\DependencyInjection\Publication\PublicationRepositoryTrait;
use App\Infrastructure\DependencyInjection\Status\StatusPersistenceTrait;
use App\Infrastructure\Repository\Membership\MemberRepositoryInterface;
use App\Infrastructure\Operation\Collection\Collection;
use App\Infrastructure\Operation\Collection\CollectionInterface;
use App\Domain\Publication\Repository\PublicationRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use function count;

class PublicationPersistence implements PublicationPersistenceInterface
{
    use MemberRepositoryTrait;
    use PublicationRepositoryTrait;
    use StatusPersistenceTrait;

    private EntityManagerInterface $entityManager;

    public function __construct(
        StatusPersistenceInterface $statusPersistence,
        PublicationRepositoryInterface $publicationRepository,
        MemberRepositoryInterface $memberRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->statusPersistence = $statusPersistence;
        $this->publicationRepository = $publicationRepository;
        $this->entityManager = $entityManager;
        $this->memberRepository = $memberRepository;
    }

    public function persistStatusPublications(
        array $statuses,
        AccessToken $identifier,
        Aggregate $aggregate = null
    ): CollectionInterface {
        $statusPersistence = $this->statusPersistence;
        $result           = $statusPersistence->persistAllStatuses(
            $statuses,
            $identifier,
            $aggregate
        );
        $normalizedStatus = $result[$statusPersistence::PROPERTY_NORMALIZED_STATUS];
        $screenName       = $result[$statusPersistence::PROPERTY_SCREEN_NAME];

        // Mark status as published
        $statusCollection = new Collection(
            $result[$statusPersistence::PROPERTY_STATUS]->toArray()
        );
        $statusCollection->map(fn(StatusInterface $status) => $status->markAsPublished());

        // Make publications
        $statusCollection = StatusToArray::fromStatusCollection($statusCollection);
        $this->publicationRepository->persistPublications($statusCollection);

        // Commit transaction
        $this->entityManager->flush();

        if (count($normalizedStatus) > 0) {
            $this->memberRepository->incrementTotalStatusesOfMemberWithName(
                count($normalizedStatus),
                $screenName
            );
        }

        return $normalizedStatus;
    }
}
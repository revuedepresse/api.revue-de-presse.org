<?php
declare(strict_types=1);

namespace App\Infrastructure\Repository\Status;

use App\Api\Entity\Aggregate;
use App\Api\Entity\ArchivedStatus;
use App\Api\Entity\Status;
use App\Domain\Status\StatusInterface;
use App\Domain\Status\TaggedStatus;
use App\Infrastructure\DependencyInjection\Status\StatusRepositoryTrait;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;
use Psr\Log\LoggerInterface;
use function sprintf;

class TaggedStatusRepository implements TaggedStatusRepositoryInterface
{
    use StatusRepositoryTrait;

    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $this->entityManager    = $entityManager;
        $this->statusRepository = $entityManager->getRepository(
            Status::class
        );
        $this->logger           = $logger;
    }

    /**
     * @param array          $properties
     * @param Aggregate|null $aggregate
     *
     * @return StatusInterface
     * @throws Exception
     */
    public function convertPropsToStatus(
        array $properties,
        ?Aggregate $aggregate
    ): StatusInterface {
        $taggedStatus = TaggedStatus::fromLegacyProps($properties);

        if ($this->statusHavingHashExists($taggedStatus->hash())) {
            return $this->statusRepository->reviseDocument($taggedStatus);
        }

        return $taggedStatus->toStatus(
            $this->entityManager,
            $this->logger,
            $aggregate
        );
    }

    /**
     * @param $hash
     *
     * @return bool
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function archivedStatusHavingHashExists(string $hash): bool
    {
        $queryBuilder = $this->entityManager
            ->getRepository(ArchivedStatus::class)
            ->createQueryBuilder('s');
        $queryBuilder->select('count(s.id) as count_')
                     ->andWhere('s.hash = :hash');

        $queryBuilder->setParameter('hash', $hash);
        $count = (int) $queryBuilder->getQuery()->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * @param $hash
     *
     * @return bool
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function statusHavingHashExists(string $hash): bool
    {
        if ($this->archivedStatusHavingHashExists($hash)) {
            return true;
        }

        $queryBuilder = $this->entityManager
            ->getRepository(Status::class)
            ->createQueryBuilder('s');
        $queryBuilder->select('count(s.id) as count_')
                     ->andWhere('s.hash = :hash');

        $queryBuilder->setParameter('hash', $hash);
        $count = (int) $queryBuilder->getQuery()->getSingleScalarResult();

        return $count > 0;
    }
}
<?php
declare(strict_types=1);

namespace App\Trends\Infrastructure\Repository;

use App\Trends\Domain\Entity\TimelyStatus;
use App\Ownership\Domain\Entity\MembersListInterface;
use App\Ownership\Domain\Entity\MembersList;
use \App\Ownership\Infrastructure\Repository\MembersListRepository;
use App\Conversation\ConversationAwareTrait;
use App\Twitter\Domain\Publication\StatusInterface;
use App\Twitter\Infrastructure\Http\SearchParams;
use App\Twitter\Domain\Publication\Repository\TimelyStatusRepositoryInterface;
use App\Twitter\Infrastructure\Clock\TimeRange\TimeRangeAwareInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;

class TimelyStatusRepository extends ServiceEntityRepository implements TimelyStatusRepositoryInterface
{
    private const TABLE_ALIAS = 't';

    use ConversationAwareTrait;

    /**
    * use PaginationAwareTrait;
 *
* /**
     * @var MembersListRepository
     */
    public MembersListRepository $aggregateRepository;

    /**
     * @throws \Exception
     */
    public function fromArray(array $properties)
    {
        $status = $this->statusRepository->findOneBy(['id' => $properties['status_id']]);
        $timelyStatus = $this->findOneBy([
            'status' => $status
        ]);

        if ($timelyStatus instanceof TimelyStatus) {
            return $timelyStatus->updateTimeRange();
        }

        $list = $this->aggregateRepository->findOneBy([
            'id' => $properties['aggregate_id'],
            'screenName' => $properties['member_name']
        ]);

        if (!($list instanceof MembersList)) {
            $list = $this->aggregateRepository->findOneBy([
                'name' => $properties['aggregate_name'],
                'screenName' => $properties['member_name']
            ]);

            if (!($list instanceof MembersList)) {
                $list = $this->aggregateRepository->make(
                    $properties['member_name'],
                    $properties['aggregate_name']
                );
                $this->aggregateRepository->save($list);
            }
        }

        return new TimelyStatus(
            $status,
            $list,
            $status->getCreatedAt()
        );
    }

    public function selectStatuses()
    {
        $queryBuilder = $this->createQueryBuilder(self::TABLE_ALIAS);
        $queryBuilder->select(
            [
                's.userAvatar as author_avatar',
                's.text',
                's.screenName as screen_name',
                's.id',
                's.statusId as status_id',
                's.starred',
                's.apiDocument original_document'
            ]
        )
            ->leftJoin('t.status', 's')
            ->orderBy('t.timeRange', 'asc')
            ->orderBy('t.publicationDateTime', 'desc')
            ->setMaxResults(50)
        ;

        return $queryBuilder;
    }

    /**
     * @throws \Exception
     */
    public function fromAggregatedStatus(
        StatusInterface $status,
        MembersListInterface $list = null
    ): TimeRangeAwareInterface {
        $timelyStatus = $this->findOneBy([
            'status' => $status
        ]);

        if ($timelyStatus instanceof TimelyStatus) {
            return $timelyStatus->updateTimeRange();
        }

        return new TimelyStatus(
            $status,
            $list,
            $status->getCreatedAt()
        );
    }

    public function saveTimelyStatus(
        TimelyStatus $timelyStatus
    ) {
        $this->getEntityManager()->persist($timelyStatus);
        $this->getEntityManager()->flush();

        return $timelyStatus;
    }

    /**
     * @param $searchParams
     * @return int
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function countTotalPages($searchParams): int
    {
        return $this->howManyPages($searchParams, self::TABLE_ALIAS);
    }

    /**
     * @throws \Exception
     */
    public function findStatuses(SearchParams $searchParams): array
    {
        $queryBuilder = $this->createQueryBuilder(self::TABLE_ALIAS);
        $this->applyCriteria($queryBuilder, $searchParams);

        $queryBuilder->setFirstResult($searchParams->getFirstItemIndex());
        $queryBuilder->setMaxResults($searchParams->getPageSize());

        $queryBuilder->orderBy(self::TABLE_ALIAS.'.publicationDateTime', 'DESC');

        $results = $queryBuilder->getQuery()->getArrayResult();
        $statuses = array_map(
            function ($status) {
                $extractedProperties = [
                    'status' => $this->extractTweetProperties(
                        [$status],
                        false)[0]
                ];

                unset($status['original_document']);
                unset($status['screen_name']);
                unset($status['author_avatar']);
                unset($status['status_id']);

                return array_merge($status, $extractedProperties);
            },
            $results
        );

        return $statuses;
    }

    /**
     * @throws \Exception
     */
    private function applyCriteria(
        QueryBuilder $queryBuilder,
        SearchParams $searchParams
    ) {
        $queryBuilder->select('s.apiDocument as original_document');
        $queryBuilder->addSelect('t.memberName as screen_name');
        $queryBuilder->addSelect('t.memberName as screenName');
        $queryBuilder->addSelect('t.aggregateName as aggregateName');
        $queryBuilder->addSelect('a.id as aggregateId');
        $queryBuilder->addSelect('s.id as id');
        $queryBuilder->addSelect('s.statusId as twitterId');
        $queryBuilder->addSelect('s.userAvatar as author_avatar');
        $queryBuilder->addSelect('s.text');
        $queryBuilder->addSelect('s.statusId as status_id');

        if ($searchParams->hasKeyword()) {
            $queryBuilder->andWhere('s.text like :keyword');
            $queryBuilder->setParameter(
                'keyword',
                sprintf('%%%s%%', $searchParams->getKeyword())
            );
        }

        $queryBuilder->innerJoin('t.status', 's');
        $queryBuilder->innerJoin('t.aggregate', 'a');

        $params = $searchParams->getParams();
        if (array_key_exists('memberName', $params)) {
            $queryBuilder->andWhere('t.memberName = :member_name');
            $queryBuilder->setParameter('member_name', $params['memberName']);
        }
    }
}

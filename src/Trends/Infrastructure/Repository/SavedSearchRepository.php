<?php

namespace App\Trends\Infrastructure\Repository;

use App\Trends\Domain\Entity\SavedSearch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityRepository;

class SavedSearchRepository extends ServiceEntityRepository
{
    /**
     * @param \stdClass $response
     * @return SavedSearch
     */
    public function make(\stdClass $response): SavedSearch
    {
        return new SavedSearch(
            $response->query,
            $response->name,
            $response->id,
            new \DateTime($response->created_at, new \DateTimeZone('UTC'))
        );
    }

    /**
     * @param SavedSearch $savedSearch
     * @return SavedSearch
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function save(SavedSearch $savedSearch): SavedSearch
    {
        $this->getEntityManager()->persist($savedSearch);
        $this->getEntityManager()->flush();

        return $savedSearch;
    }

}

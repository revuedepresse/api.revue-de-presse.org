<?php
declare(strict_types=1);

namespace App\Api\AccessToken\Repository;

use App\Api\Entity\Token;
use App\Api\Entity\TokenInterface;

/**
 * @method Token|null find($id, $lockMode = null, $lockVersion = null)
 * @method Token|null findOneBy(array $criteria, array $orderBy = null)
 * @method Token[]    findAll()
 * @method Token[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
interface TokenRepositoryInterface
{
    public function findTokenOtherThan(string $token): ?TokenInterface;

    public function howManyUnfrozenTokenAreThere(): int;
}
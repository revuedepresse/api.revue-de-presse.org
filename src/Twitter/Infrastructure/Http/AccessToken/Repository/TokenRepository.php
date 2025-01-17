<?php
declare(strict_types=1);

namespace App\Twitter\Infrastructure\Http\AccessToken\Repository;

use App\Twitter\Infrastructure\Http\Entity\TokenInterface;
use App\Twitter\Infrastructure\Http\Exception\UnavailableTokenException;
use App\Twitter\Infrastructure\Database\Connection\ConnectionAwareInterface;
use App\Twitter\Infrastructure\DependencyInjection\LoggerTrait;
use DateTime;
use DateTimeZone;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\NoResultException;

use Exception;
use Psr\Log\LoggerInterface;

use App\Twitter\Infrastructure\Http\Entity\Token,
    App\Twitter\Infrastructure\Http\Entity\TokenType;

/**
 * @author revue-de-presse.org <thierrymarianne@users.noreply.github.com>
 *
 * @method TokenInterface|null find($id, $lockMode = null, $lockVersion = null)
 * @method TokenInterface|null findOneBy(array $criteria, array $orderBy = null)
 * @method TokenInterface[]    findAll()
 * @method TokenInterface[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TokenRepository extends ServiceEntityRepository implements TokenRepositoryInterface, ConnectionAwareInterface
{
    use LoggerTrait;

    /**
     * @param $properties
     *
     * @return Token
     */
    public function makeToken($properties): TokenInterface
    {
        $token = new Token();

        $now = new DateTime();
        $token->setCreatedAt($now);
        $token->setUpdatedAt($now);

        $tokenRepository = $this->getEntityManager()->getRepository('App\Twitter\Infrastructure\Http\Entity\TokenType');

        /** @var TokenType $tokenType */
        $tokenType = $tokenRepository->findOneBy(['name' => TokenType::USER]);
        $token->setType($tokenType);

        $token->setOauthToken($properties['oauth_token']);
        $token->setOauthTokenSecret($properties['oauth_token_secret']);

        // Ensure the newly created token is not frozen yet
        // equivalent to setting the frozen until date in the past
        $token->setFrozenUntil(new DateTime('now - 15min'));

        return $token;
    }

    public function ensureTokenExists(
        $oauthToken,
        $oauthTokenSecret,
        $consumerKey,
        $consumerSecret
    ): void {
        if ($this->findOneBy(['oauthToken' => $oauthToken]) !== null) {
            return;
        }

        $token = $this->makeToken([
            'oauth_token' => $oauthToken,
            'oauth_token_secret' => $oauthTokenSecret
        ]);
        $token->setConsumerKey($consumerKey);
        $token->setConsumerSecret($consumerSecret);

        $this->save($token);
    }

    /**
     * @param $oauthToken
     * @param string $until
     * @throws Exception
     */
    public function freezeToken($oauthToken, $until = 'now + 15min'): void
    {
        /** @var Token $token */
        $token = $this->findOneBy(['oauthToken' => $oauthToken]);
        $token->setFrozenUntil(new DateTime($until));

        $this->save($token);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws Exception
     */
    public function refreshFreezeCondition(
        string $oauthToken,
        LoggerInterface $logger
    ): TokenInterface {
        $frozen = false;

        $token = $this->findOneBy(['oauthToken' => $oauthToken]);

        if ($token === null) {
            $token = $this->makeToken(['oauth_token' => $oauthToken, 'oauth_token_secret' => '']);
            $this->save($token);

            $logger->info('[token creation] ' . $token->getOauthToken());
        } elseif ($this->isTokenFrozen($token)) {
            /**
             * The token is frozen if the "frozen until" date is in the future
             */
            $frozen = true;
        }
        /**
         *  else {
         *      The token was frozen but not anymore as "frozen until" date is now in the past
         *  }
         */

        $token->setFrozen($frozen);

        return $token;
    }

    /**
     * @param Token $token
     *
     * @return bool
     * @throws Exception
     */
    protected function isTokenFrozen(Token $token): bool
    {
        return $token->getFrozenUntil() !== null &&
            $token->getFrozenUntil()->getTimestamp() >
                (new DateTime('now', new DateTimeZone('UTC')))
                    ->getTimestamp();
    }

    /**
     * @param $oauthToken
     *
     * @return bool
     * @throws NonUniqueResultException
     */
    public function isOauthTokenFrozen($oauthToken): bool
    {
        $token = $this->findUnfrozenToken($oauthToken);

        return !($token instanceof Token);
    }

    /**
     * @param string $token
     *
     * @return Token|null
     * @throws NonUniqueResultException
     */
    public function findUnfrozenToken(string $token): ?Token
    {
        $queryBuilder = $this->createQueryBuilder('t');

        $queryBuilder->andWhere('t.oauthToken = :token');
        $queryBuilder->setParameter('token', $token);

        $this->applyUnfrozenTokenCriteria($queryBuilder);

        try {
            return $queryBuilder->getQuery()->getSingleResult();
        } catch (NoResultException $exception) {
            return null;
        }
    }

    /**
     * @return mixed|null
     * @throws NonUniqueResultException
     */
    public function howManyUnfrozenTokenAreThere(): int
    {
        $queryBuilder = $this->createQueryBuilder('t');
        $queryBuilder->select('COUNT(t.id) as count_');

        $this->applyUnfrozenTokenCriteria($this->createQueryBuilder('t'));

        try {
            return $queryBuilder->getQuery()->getSingleResult()['count_'];
        } catch (NoResultException $exception) {
            UnavailableTokenException::throws();
        }
    }

    /**
     * @param QueryBuilder $queryBuilder
     *
     * @return QueryBuilder
     */
    private function applyUnfrozenTokenCriteria(QueryBuilder $queryBuilder): QueryBuilder {
        $queryBuilder->andWhere('t.type = :type');
        $tokenRepository = $this->getEntityManager()->getRepository(TokenType::class);
        $tokenType = $tokenRepository->findOneBy(['name' => TokenType::USER]);
        $queryBuilder->setParameter('type', $tokenType);

        $queryBuilder->andWhere('t.oauthTokenSecret IS NOT NULL');

        $queryBuilder->andWhere('(t.frozenUntil IS NULL or t.frozenUntil < NOW())');

        return $queryBuilder->setMaxResults(1);
    }

    /**
     * @return mixed|null
     * @throws NonUniqueResultException
     */
    public function findFirstUnfrozenToken(): ?TokenInterface
    {
        $queryBuilder = $this->createQueryBuilder('t');

        $this->applyUnfrozenTokenCriteria($queryBuilder);

        try {
            return $queryBuilder->getQuery()->getSingleResult();
        } catch (NoResultException $exception) {
            return null;
        }
    }

    /**
     * @throws NonUniqueResultException
     */
    public function findFirstFrozenToken(): ?TokenInterface
    {
        $queryBuilder = $this->createQueryBuilder('t');

        $tokenRepository = $this->getEntityManager()->getRepository(TokenType::class);
        $tokenType = $tokenRepository->findOneBy(['name' => TokenType::USER]);

        $queryBuilder->andWhere('t.type = :type');
        $queryBuilder->setParameter('type', $tokenType);

        $queryBuilder->andWhere('t.oauthTokenSecret IS NOT NULL');

        $queryBuilder->andWhere('t.frozenUntil > :now');
        $queryBuilder->setParameter(
            'now',
            new DateTime('now', new DateTimeZone('UTC'))
        );

        $queryBuilder->setMaxResults(1);
        $queryBuilder->orderBy('t.frozenUntil', 'ASC');

        try {
            return $queryBuilder->getQuery()->getSingleResult();
        } catch (NoResultException $exception) {
            return null;
        }
    }

    /**
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function findTokenOtherThan(string $token): ?TokenInterface
    {
        $queryBuilder = $this->createQueryBuilder('t');
        $queryBuilder->andWhere('t.oauthToken != :token');
        $queryBuilder->setParameter('token', $token);

        $queryBuilder->andWhere('t.frozenUntil < :now');
        $queryBuilder->setParameter('now', new DateTime('now', new DateTimeZone('UTC')));

        $queryBuilder->setMaxResults(1);

        return $queryBuilder->getQuery()->getSingleResult();
    }

    private function save(Token $token): void
    {
        $exception = new \Exception("TODO: Figuring which class is responsible for trying to record tokens");

        // NOOP
        $this->logger->error(
            $exception->getMessage(),
            [
                'token' => $token->getOAuthToken(),
                'stacktrace' => $exception->getTraceAsString()
            ]
        );
    }

    public function reconnect(): void {
        $entityManager = $this->getEntityManager();
        $entityManager->getConnection()->connect();
    }
}

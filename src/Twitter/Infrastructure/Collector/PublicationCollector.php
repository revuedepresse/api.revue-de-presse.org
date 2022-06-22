<?php
declare(strict_types=1);

namespace App\Twitter\Infrastructure\Collector;

use App\Twitter\Domain\Collector\PublicationCollectorInterface;
use App\Twitter\Infrastructure\Api\Entity\FreezableToken;
use App\Twitter\Infrastructure\Api\Accessor\Exception\ApiRateLimitingException;
use App\Twitter\Infrastructure\Api\Accessor\Exception\NotFoundStatusException;
use App\Twitter\Infrastructure\Api\Accessor\Exception\ReadOnlyApplicationException;
use App\Twitter\Infrastructure\Api\Accessor\Exception\UnexpectedApiResponseException;
use App\Twitter\Infrastructure\Api\Entity\Token;
use App\Twitter\Domain\Api\Model\TokenInterface;
use App\Twitter\Infrastructure\Curation\CurationSelectors;
use App\Twitter\Domain\Curation\CurationSelectorsInterface;
use App\Twitter\Domain\Curation\Exception\NoRemainingPublicationException;
use App\Twitter\Domain\Publication\Exception\LockedPublishersListException;
use App\Twitter\Domain\Publication\PublishersListInterface;
use App\Twitter\Infrastructure\Amqp\Message\FetchPublicationInterface;
use App\Twitter\Infrastructure\DependencyInjection\{Api\ApiAccessorTrait,
    Api\ApiLimitModeratorTrait,
    Api\StatusAccessorTrait,
    Collection\InterruptibleCollectDeciderTrait,
    Collection\MemberProfileCollectedEventRepositoryTrait,
    Collection\PublicationBatchCollectedEventRepositoryTrait,
    LoggerTrait,
    Membership\MemberRepositoryTrait,
    Membership\WhispererIdentificationTrait,
    Membership\WhispererRepositoryTrait,
    Publication\PublishersListRepositoryTrait,
    Publication\PublicationPersistenceTrait,
    Status\StatusLoggerTrait,
    Status\StatusPersistenceTrait,
    Status\StatusRepositoryTrait,
    TokenRepositoryTrait};
use App\Twitter\Infrastructure\DependencyInjection\TranslatorTrait;
use App\Twitter\Infrastructure\Collector\Exception\RateLimitedException;
use App\Twitter\Infrastructure\Collector\Exception\SkipCollectException;
use App\Membership\Domain\Model\MemberInterface;
use App\Twitter\Infrastructure\Exception\BadAuthenticationDataException;
use App\Twitter\Infrastructure\Exception\InconsistentTokenRepository;
use App\Twitter\Infrastructure\Exception\NotFoundMemberException;
use App\Twitter\Infrastructure\Exception\ProtectedAccountException;
use App\Twitter\Infrastructure\Exception\SuspendedAccountException;
use App\Twitter\Infrastructure\Exception\UnavailableResourceException;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\ConstraintViolationException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\OptimisticLockException;
use Exception;
use LogicException;
use Psr\Log\LoggerInterface;
use ReflectionException;
use stdClass;
use function array_key_exists;
use function count;

class PublicationCollector implements PublicationCollectorInterface
{
    use ApiAccessorTrait;
    use ApiLimitModeratorTrait;
    use LoggerTrait;
    use InterruptibleCollectDeciderTrait;
    use MemberProfileCollectedEventRepositoryTrait;
    use MemberRepositoryTrait;
    use PublicationBatchCollectedEventRepositoryTrait;
    use PublishersListRepositoryTrait;
    use PublicationPersistenceTrait;
    use StatusLoggerTrait;
    use StatusPersistenceTrait;
    use StatusAccessorTrait;
    use StatusRepositoryTrait;
    use TokenRepositoryTrait;
    use TranslatorTrait;
    use WhispererIdentificationTrait;
    use WhispererRepositoryTrait;

    private const MESSAGE_OPTION_TOKEN = 'oauth';

    public LoggerInterface $twitterApiLogger;

    private CurationSelectorsInterface $selectors;

    /**
     * @param array $options
     * @param bool  $greedy
     * @param bool  $discoverPublicationsWithMaxId
     *
     * @return bool
     * @throws ApiRateLimitingException
     * @throws BadAuthenticationDataException
     * @throws InconsistentTokenRepository
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws NotFoundMemberException
     * @throws NotFoundStatusException
     * @throws OptimisticLockException
     * @throws ProtectedAccountException
     * @throws ReadOnlyApplicationException
     * @throws ReflectionException
     * @throws SuspendedAccountException
     * @throws UnavailableResourceException
     * @throws UnexpectedApiResponseException
     * @throws Exception
     */
    public function collect(
        array $options,
        $greedy = false,
        $discoverPublicationsWithMaxId = true
    ): bool {
        $success = false;

        $this->selectors = CurationSelectors::fromArray($options);

        try {
            $this->interruptibleCollectDecider->decideWhetherCollectShouldBeSkipped(
                $this->selectors,
                $options
            );
        } catch (SkipCollectException $exception) {
            if ($exception instanceof RateLimitedException) {
                unset($exception);

                return false; // unsuccessfully made an attempt to collect statuses
            }

            return true;
        } finally {
            $this->updateLastStatusPublicationDate($options);
        }

        if ($this->selectors->oneOfTheOptionsIsActive()) {
            $options = $this->removeCollectOptions(
                $options
            );

            try {
                $this->lockPublishersList();
            } catch (LockedPublishersListException $exception) {
                $this->logger->info($exception->getMessage());

                return true;
            }
        }

        if (
            !$this->isTwitterApiAvailable()
            && ($remainingItemsToCollect = $this->remainingItemsToCollect($options))
        ) {
            $this->unlockPublishersList();

            /**
             * Marks the collect as successful when there is no remaining status
             * or when Twitter API is not available
             */
            return isset($remainingItemsToCollect) ?: false;
        }

        if ($this->selectors->shouldLookUpPublicationsWithMinId(
            $this->statusRepository,
            $this->memberRepository
        )) {
            $discoverPublicationsWithMaxId = false;
        }

        $options = $this->statusAccessor->updateExtremum(
            $this->selectors,
            $options,
            $discoverPublicationsWithMaxId
        );

        try {
            $success = $this->tryCollectingFurther(
                $options,
                $greedy,
                $discoverPublicationsWithMaxId
            );
        } catch (BadAuthenticationDataException $exception) {
            $token = $this->tokenRepository->findFirstUnfrozenToken();
            if (!($token instanceof Token)) {
                return false;
            }

            $options = $this->setUpAccessorWithFirstAvailableToken($token, $options);
            $success = $this->tryCollectingFurther($options, $greedy, $discoverPublicationsWithMaxId);
        } catch (SuspendedAccountException|NotFoundMemberException|ProtectedAccountException $exception) {
            UnavailableResourceException::handleUnavailableMemberException(
                $exception,
                $this->logger,
                $options
            );

            // Figuring out a member is now protected, suspended or not found is considered to be a "success",
            // provided the workers would not call the API on behalf of them
            $success = true;
        } catch (ConstraintViolationException $constraintViolationException) {
            $this->logger->critical(
                $constraintViolationException->getMessage(),
                ['stacktrace' => $constraintViolationException->getTraceAsString()]
            );
            $success = false;
        } catch (\Throwable $exception) {
            $this->logger->error(
                sprintf(
                    '[from %s %s]',
                    __METHOD__,
                    $exception->getMessage()
                ),
                ['stacktrace' => $exception->getTraceAsString()]
            );
            $success = false;
        } finally {
            $this->unlockPublishersList();
        }

        return $success;
    }

    /**
     * @param array $options
     */
    private function updateLastStatusPublicationDate(array $options): void
    {
        try {
            $this->statusRepository->updateLastStatusPublicationDate(
                $options[FetchPublicationInterface::SCREEN_NAME]
            );
        } catch (NotFoundStatusException $exception) {
            $this->logger->info($exception->getMessage());
        }
    }

    /**
     * @param $lastCollectionBatchSize
     * @param $totalCollectedStatuses
     *
     * @return bool
     */
    public function collectedAllAvailableStatuses(
        $lastCollectionBatchSize,
        $totalCollectedStatuses
    ): bool {
        return !$this->justCollectedSomeStatuses($lastCollectionBatchSize)
            && $this->hitCollectionLimit($totalCollectedStatuses);
    }

    /**
     * @param $statuses
     *
     * @return bool
     */
    public function hitCollectionLimit($statuses): bool
    {
        return $statuses >= (CurationSelectorsInterface::MAX_AVAILABLE_TWEETS_PER_USER - 100);
    }

    /**
     * @param $statuses
     *
     * @return bool
     */
    public function justCollectedSomeStatuses($statuses): bool
    {
        return $statuses !== null && $statuses > 0;
    }

    /**
     * @param array $options
     *
     * @return mixed
     */
    public function removeCollectOptions(
        $options
    ) {
        if ($this->selectors->dateBeforeWhichPublicationsAreToBeCollected()) {
            unset($options[FetchPublicationInterface::BEFORE]);
        }
        if (array_key_exists(FetchPublicationInterface::PUBLISHERS_LIST_ID, $options)) {
            unset($options[FetchPublicationInterface::PUBLISHERS_LIST_ID]);
        }

        return $options;
    }

    /**
     * @param array $oauthTokens
     *
     * @return $this
     */
    public function setupAccessor(array $oauthTokens): self
    {
        $token = new Token();
        $token->setAccessToken($oauthTokens[TokenInterface::FIELD_TOKEN]);
        $token->setAccessTokenSecret($oauthTokens[TokenInterface::FIELD_SECRET]);

        $this->apiAccessor->fromToken($token);

        /** @var Token token */
        $token = $this->tokenRepository->findOneBy(
            ['oauthToken' => $oauthTokens[TokenInterface::FIELD_TOKEN]]
        );

        if (!$token instanceof Token) {
            $token = $this->tokenRepository->findFirstUnfrozenToken();
        }

        $this->apiAccessor->setConsumerKey($token->consumerKey);
        $this->apiAccessor->setConsumerSecret($token->consumerSecret);

        return $this;
    }

    protected function guardAgainstNoRemainingPublicationToBeCollected(
        $options,
        bool $betweenPublicationDateOfLastOneSavedAndNow,
        $statuses
    ): void {
        $statusesIds   = $this->getExtremeStatusesIdsFor($options);
        $firstStatusId = $statusesIds['min_id'];
        $lastStatusId  = $statusesIds['max_id'];

        // When we didn't fetch publications between the last one saved and now,
        // both first and last status were declared
        // some publications were retrieved and
        // no boundaries were crosse
        if (
            !$betweenPublicationDateOfLastOneSavedAndNow
            && $firstStatusId !== null
            && $lastStatusId !== null
            && count($statuses) > 0
            && ($statuses[count($statuses) - 1]->id >= (int) $firstStatusId)
            && ($statuses[0]->id <= (int) $lastStatusId)
        ) {
            throw new NoRemainingPublicationException(
                'There is no remaining publication to be collected.'
            );
        }
    }

    /**
     * @return bool
     */
    protected function isApiAvailable(): bool
    {
        $availableApi = false;

        if (!$this->apiAccessor->isApiLimitReached()) {
            return true;
        }

        try {
            if (!$this->apiAccessor->isApiRateLimitReached('/statuses/user_timeline')) {
                $availableApi = true;
            }
        } catch (Exception $exception) {
            $this->twitterApiLogger->info('[error message] Testing for API availability: ' . $exception->getMessage());
            $this->twitterApiLogger->info('[error code] ' . (int) $exception->getCode());

            if ($exception->getCode() === $this->apiAccessor->getEmptyReplyErrorCode()) {
                $availableApi = true;
            } else {
                $this->tokenRepository->freezeToken(
                    FreezableToken::fromAccessToken(
                        $this->apiAccessor->accessToken(),
                        $this->apiAccessor->consumerKey()
                    )
                );
            }
        }

        return $availableApi;
    }

    protected function isApiAvailableForToken(TokenInterface $token): bool
    {
        $this->setupAccessor(
            [
                TokenInterface::FIELD_TOKEN  => $token->getAccessToken(),
                TokenInterface::FIELD_SECRET => $token->getAccessTokenSecret()
            ]
        );

        return $this->isApiAvailable();
    }

    /**
     * @throws Exception
     */
    protected function isTwitterApiAvailable(): bool
    {
        $availableApi = false;

        $token = $this->tokenRepository->findByUserToken($this->apiAccessor->userToken);

        if ($token->isNotFrozen()) {
            $availableApi = $this->isApiAvailable();
        }

        $token = $this->tokenRepository->findFirstUnfrozenToken();

        if (!$availableApi && $token !== null) {
            $frozenUntil = $token->getFrozenUntil();
            if ($frozenUntil === null) {
                return true;
            }

            $now        = new DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $timeout    = $frozenUntil->getTimestamp() - $now->getTimestamp();
            $oauthToken = $token->getAccessToken();

            $availableApi = $this->isApiAvailableForToken($token);
            while (
                !$availableApi &&
                ($token = $this->tokenRepository->findFirstUnfrozenToken()) instanceof TokenInterface
            ) {
                $availableApi = $this->isApiAvailableForToken($token);
                if (!$availableApi) {
                    $timeout = min(abs($timeout), abs($token->getFrozenUntil()->getTimestamp() - $now->getTimestamp()));
                }

                $oauthToken = $token->getAccessToken();
            }

            if (!$availableApi) {
                $this->logger->info('The API is not available right now.');
                $this->moderator->waitFor(
                    $timeout,
                    [
                        '{{ token }}' => substr($oauthToken, 0, 8),
                    ]
                );
            }
        }

        if (!$availableApi) {
            return $this->interruptibleCollectDecider->delayingConsumption();
        }

        return true;
    }

    protected function remainingItemsToCollect(array $options): bool
    {
        return $this->remainingStatuses($options);
    }

    /**
     * @param $options
     *
     * @return bool
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    protected function remainingStatuses($options): bool
    {
        $serializedStatusCount = $this->statusRepository->countHowManyStatusesFor(
            $options[FetchPublicationInterface::SCREEN_NAME]
        );
        $existingStatus        = $this->translator->trans(
            'logs.info.status_existing',
            [
                'count'        => $serializedStatusCount,
                'total_status' => $serializedStatusCount,
                'member'       => $options[FetchPublicationInterface::SCREEN_NAME],
            ],
            'logs'
        );
        $this->logger->info($existingStatus);

        $memberProfile = $this->collectMemberProfile(
            $options[FetchPublicationInterface::SCREEN_NAME]
        );
        if (!isset($memberProfile->statuses_count)) {
            $memberProfile->statuses_count = 0;
        }

        /**
         * Twitter allows 3200 past tweets at most to be retrieved for any given user
         */
        $statusesCount    = max(
            $memberProfile->statuses_count,
            CurationSelectorsInterface::MAX_AVAILABLE_TWEETS_PER_USER
        );
        $discoveredStatus = $this->translator->trans(
            'logs.info.status_discovered',
            [
                'member'       => $options[FetchPublicationInterface::SCREEN_NAME],
                'count'        => $statusesCount,
                'total_status' => $statusesCount,
            ],
            'logs'
        );
        $this->logger->info($discoveredStatus);

        return $serializedStatusCount < $statusesCount;
    }

    /**
     * @param                             $options
     * @param CurationSelectorsInterface  $selectors
     *
     * @return int|null
     * @throws ProtectedAccountException
     */
    protected function saveStatusesMatchingCriteria(
        $options,
        CurationSelectorsInterface $selectors
    ): ?int {
        $options  = $this->declareOptionsToCollectStatuses($options);

        try {
            $statuses = $this->publicationBatchCollectedEventRepository
                ->collectedPublicationBatch($selectors, $options);
        } catch (ApiRateLimitingException $e) {
            if ($this->isTwitterApiAvailable()) {
                return $this->saveStatusesMatchingCriteria($options, $selectors);
            }
        }

        if ($statuses instanceof stdClass && isset($statuses->error)) {
            throw new ProtectedAccountException(
                $statuses->error,
                $this->apiAccessor::ERROR_PROTECTED_ACCOUNT
            );
        }

        $lookingBetweenLastPublicationAndNow = $this->isLookingBetweenPublicationDateOfLastOneSavedAndNow($options);

        /** @var array $statuses */
        if (count($statuses) > 0) {
            $this->safelyDeclareExtremum(
                $statuses,
                $lookingBetweenLastPublicationAndNow,
                $options[FetchPublicationInterface::SCREEN_NAME]
            );
        }

        try {
            $this->guardAgainstNoRemainingPublicationToBeCollected(
                $options,
                $lookingBetweenLastPublicationAndNow,
                $statuses
            );
        } catch (NoRemainingPublicationException $exception) {
            $this->logger->info($exception->getMessage());

            return 0;
        }

        $lastCollectionBatchSize = $this->statusPersistence->savePublicationsForScreenName(
            $statuses,
            $options[FetchPublicationInterface::SCREEN_NAME],
            $selectors
        );

        $this->whispererIdentification->identifyWhisperer(
            $selectors,
            $options,
            $options[FetchPublicationInterface::SCREEN_NAME],
            (int) $lastCollectionBatchSize
        );

        return $lastCollectionBatchSize;
    }

    private function collectMemberProfile(string $screenName)
    {
        $eventRepository = $this->memberProfileCollectedEventRepository;

        return $eventRepository->collectedMemberProfile(
            $this->apiAccessor,
            [$eventRepository::OPTION_SCREEN_NAME => $screenName]
        );
    }

    /**
     * @param array  $statuses
     * @param bool   $shouldDeclareMaximumStatusId
     *
     * @return MemberInterface
     */
    private function declareExtremumIdForMember(
        array $statuses,
        bool $shouldDeclareMaximumStatusId
    ): MemberInterface {
        if (empty($statuses)) {
            throw new LogicException(
                'There should be at least one status'
            );
        }

        if ($shouldDeclareMaximumStatusId) {
            $lastStatusFetched = $statuses[0];

            return $this->statusRepository->declareMaximumStatusId($lastStatusFetched);
        }

        $firstStatusFetched = $statuses[count($statuses) - 1];

        return $this->statusRepository->declareMinimumStatusId($firstStatusFetched);
    }

    /**
     * @param $options
     *
     * @return mixed
     */
    private function declareOptionsToCollectStatuses($options)
    {
        if (array_key_exists('max_id', $options) && is_infinite($options['max_id'])) {
            unset($options['max_id']);
        }

        return $options;
    }

    private function lockPublishersList(): void
    {
        if (!$this->isCollectingStatusesForAggregate()) {
            return;
        }

        $publishersList = $this->publishersListRepository->findOneBy(
            ['id' => $this->selectors->publishersListId()]
        );

        if (!$publishersList instanceof PublishersListInterface) {
            return;
        }

        if ($publishersList->isLocked()) {
            throw new LockedPublishersListException(
                'Won\'t process message for already locked aggregate #%d',
                $publishersList
            );
        }

        $this->logger->info(
            sprintf(
                'About to lock processing of Twitter list #%d',
                $publishersList->getId()
            )
        );

        $this->publishersListRepository->lockAggregate($publishersList);
    }

    /**
     * @param $options
     *
     * @return array
     */
    private function getExtremeStatusesIdsFor($options): array
    {
        return $this->statusRepository->getIdsOfExtremeStatusesSavedForMemberHavingScreenName(
            $options[FetchPublicationInterface::SCREEN_NAME]
        );
    }

    /**
     * @return bool
     */
    private function isCollectingStatusesForAggregate(): bool
    {
        return $this->selectors->publishersListId() !== null;
    }

    /**
     * @param $options
     *
     * @return bool
     */
    private function isLookingBetweenPublicationDateOfLastOneSavedAndNow($options): bool
    {
        if (array_key_exists('since_id', $options)) {
            return true;
        }

        return array_key_exists('max_id', $options) && is_infinite($options['max_id']);
    }

    /**
     * @param        $statuses
     * @param        $shouldDeclareMaximumStatusId
     * @param string $memberName
     */
    private function safelyDeclareExtremum(
        $statuses,
        $shouldDeclareMaximumStatusId,
        string $memberName
    ): void {
        try {
            $this->declareExtremumIdForMember(
                $statuses,
                $shouldDeclareMaximumStatusId
            );
        } catch (NotFoundMemberException $exception) {
            $this->apiAccessor->ensureMemberHavingNameExists($exception->screenName);

            try {
                $this->declareExtremumIdForMember(
                    $statuses,
                    $shouldDeclareMaximumStatusId
                );
            } catch (NotFoundMemberException $exception) {
                $this->apiAccessor->ensureMemberHavingNameExists($exception->screenName);
                $this->declareExtremumIdForMember(
                    $statuses,
                    $shouldDeclareMaximumStatusId
                );
            }
        }
    }

    /**
     * @param Token $token
     * @param array $options
     *
     * @return array
     * @throws Exception
     */
    private function setUpAccessorWithFirstAvailableToken(
        Token $token,
        array $options
    ): array {
        $options[self::MESSAGE_OPTION_TOKEN] = $token->getAccessToken();
        $this->setupAccessor(
            [
                TokenInterface::FIELD_TOKEN  => $options[self::MESSAGE_OPTION_TOKEN],
                TokenInterface::FIELD_SECRET => $token->getAccessTokenSecret()
            ]
        );

        return $options;
    }

    /**
     * @param $options
     * @param $greedy
     * @param $discoverPublicationsWithMaxId
     *
     * @return bool
     * @throws ApiRateLimitingException
     * @throws BadAuthenticationDataException
     * @throws InconsistentTokenRepository
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws NotFoundMemberException
     * @throws NotFoundStatusException
     * @throws OptimisticLockException
     * @throws ProtectedAccountException
     * @throws ReadOnlyApplicationException
     * @throws ReflectionException
     * @throws SuspendedAccountException
     * @throws UnavailableResourceException
     * @throws UnexpectedApiResponseException
     */
    private function tryCollectingFurther($options, $greedy, $discoverPublicationsWithMaxId): bool
    {
        $success = true;

        $this->collectStatusLogger->logIntentionWithRegardsToAggregate(
            $options,
            $this->selectors
        );

        $lastCollectionBatchSize = $this->saveStatusesMatchingCriteria(
            $options,
            $this->selectors
        );

        if (
            $discoverPublicationsWithMaxId
            || (
                $lastCollectionBatchSize !== null
                && $lastCollectionBatchSize === CurationSelectorsInterface::MAX_BATCH_SIZE
            )
        ) {
            // When some of the last batch of publications have been collected for the first time,
            // and we were discovering publication in the past,
            // keep retrieving status in the past,
            // otherwise start collecting publication never seen before,
            // which have been more recently published
            $discoverPublicationsWithMaxId = $lastCollectionBatchSize !== null &&
                $discoverPublicationsWithMaxId;

            if ($greedy) {
                $options[FetchPublicationInterface::PUBLISHERS_LIST_ID] = $this->selectors->publishersListId();
                $options[FetchPublicationInterface::BEFORE]              =
                    $this->selectors->dateBeforeWhichPublicationsAreToBeCollected();

                $success = $this->collect(
                    $options,
                    $greedy,
                    $discoverPublicationsWithMaxId
                );

                $discoverPublicationWithMinId = !$discoverPublicationsWithMaxId;
                if (
                    $discoverPublicationWithMinId
                    && $this->selectors->dateBeforeWhichPublicationsAreToBeCollected() === null
                ) {
                    unset($options[FetchPublicationInterface::PUBLISHERS_LIST_ID]);

                    $options = $this->statusAccessor->updateExtremum(
                        $this->selectors,
                        $options,
                        $discoverPublicationsWithMaxId = false
                    );
                    $options = $this->apiAccessor->guessMaxId(
                        $options,
                        $this->selectors->shouldLookUpPublicationsWithMinId(
                            $this->statusRepository,
                            $this->memberRepository
                        )
                    );

                    $this->saveStatusesMatchingCriteria(
                        $options,
                        $this->selectors
                    );
                }
            }
        }

        return $success;
    }

    private function unlockPublishersList(): void
    {
        if ($this->isCollectingStatusesForAggregate()) {
            $publishersList = $this->publishersListRepository->findOneBy(
                ['id' => $this->selectors->publishersListId()]
            );
            if ($publishersList instanceof PublishersListInterface) {
                $this->publishersListRepository->unlockPublishersList($publishersList);
                $this->logger->info(
                    sprintf(
                        'Unlocked Twitter list of id #%d',
                        $publishersList->getId()
                    )
                );
            }
        }
    }
}

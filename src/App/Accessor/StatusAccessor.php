<?php

namespace App\Accessor;

use App\Member\MemberInterface;
use App\Status\Entity\NullStatus;
use App\Status\Repository\NotFoundStatusRepository;
use Doctrine\ORM\EntityManager;
use WeavingTheWeb\Bundle\ApiBundle\Entity\ArchivedStatus;
use WeavingTheWeb\Bundle\ApiBundle\Entity\Status;
use WeavingTheWeb\Bundle\ApiBundle\Repository\ArchivedStatusRepository;
use WeavingTheWeb\Bundle\ApiBundle\Repository\StatusRepository;
use WeavingTheWeb\Bundle\TwitterBundle\Api\Accessor;
use WeavingTheWeb\Bundle\TwitterBundle\Exception\NotFoundMemberException;
use WTW\UserBundle\Repository\UserRepository;

class StatusAccessor
{
    /**
     * @var bool
     */
    public $accessingInternalApi = true;

    /**
     * @var ArchivedStatusRepository
     */
    public $archivedStatusRepository;

    /**
     * @var EntityManager
     */
    public $entityManager;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    public $logger;

    /**
     * @var NotFoundStatusRepository
     */
    public $notFoundStatusRepository;

    /**
     * @var StatusRepository
     */
    public $statusRepository;

    /**
     * @var UserRepository
     */
    public $userManager;

    /**
     * @var Accessor
     */
    public $accessor;

    /**
     * @param string $identifier
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function declareStatusNotFoundByIdentifier(string $identifier)
    {
        $status = $this->statusRepository->findOneBy(['statusId' => $identifier]);
        if (is_null($status)) {
            $status = $this->archivedStatusRepository
                ->findOneBy(['statusId' => $identifier]);
        }

        $existingRecord = false;
        if ($status instanceof Status) {
            $existingRecord = !is_null($this->notFoundStatusRepository->findOneBy(['status' => $status]));
        }

        if ($status instanceof ArchivedStatus) {
            $existingRecord = !is_null($this->notFoundStatusRepository->findOneBy(['archivedStatus' => $status]));
        }

        if ($existingRecord) {
            return;
        }

        if (is_null($status)) {
            return;
        }

        $notFoundStatus = $this->notFoundStatusRepository->markStatusAsNotFound($status);

        $this->entityManager->persist($notFoundStatus);
        $this->entityManager->flush();
    }

    /**
     * @param string $identifier
     * @param bool   $skipExistingStatus
     * @param bool   $extraProperties
     * @return \API|NullStatus|array|mixed|null|object|\stdClass
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \WeavingTheWeb\Bundle\TwitterBundle\Exception\SuspendedAccountException
     * @throws \WeavingTheWeb\Bundle\TwitterBundle\Exception\UnavailableResourceException
     */
    public function refreshStatusByIdentifier(
        string $identifier,
        bool $skipExistingStatus = false,
        bool $extractProperties = true
    ) {
        $this->statusRepository->shouldExtractProperties = $extractProperties;

        $status = null;
        if (!$skipExistingStatus) {
            $status = $this->statusRepository->findStatusIdentifiedBy($identifier);
        }

        if (!is_null($status) && !empty($status)) {
            return $status;
        }

        $this->accessor->shouldRaiseExceptionOnApiLimit = true;
        $status = $this->accessor->showStatus($identifier);

        $this->entityManager->clear();

        try {
            $this->statusRepository->saveStatuses(
                [$status],
                $this->accessor->userToken,
                null,
                $this->logger
            );
        } catch (NotFoundMemberException $notFoundMemberException) {
            return $this->findStatusIdentifiedBy($identifier);
        } catch (\Exception $exception) {
            $this->logger->info($exception->getMessage());
        }

        return $this->findStatusIdentifiedBy($identifier);
    }

    /**
     * @param string   $memberName
     * @param int|null $memberId
     * @return \API|MemberInterface|mixed|null|object|\stdClass
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \WeavingTheWeb\Bundle\TwitterBundle\Exception\SuspendedAccountException
     * @throws \WeavingTheWeb\Bundle\TwitterBundle\Exception\UnavailableResourceException
     */
    public function ensureMemberHavingNameExists(string $memberName)
    {
        $member = $this->userManager->findOneBy(['twitter_username' => $memberName]);
        if ($member instanceof MemberInterface) {
            return $member;
        }

        $fetchedMember = $this->accessor->showUser($memberName);
        $member = $this->userManager->findOneBy(['twitterID' => $fetchedMember->id]);
        if ($member instanceof MemberInterface) {
            return $member;
        }

        return $this->userManager->saveMember(
            $this->userManager->make(
                $fetchedMember->id,
                $memberName
            )
        );
    }

    /**
     * @param string $identifier
     * @param bool   $extractProperties
     * @return NullStatus|array
     */
    private function findStatusIdentifiedBy(string $identifier)
    {
        $status = $this->statusRepository->findStatusIdentifiedBy($identifier);

        if (is_null($status)) {
            return new NullStatus();
        }

        return $status;
    }
}

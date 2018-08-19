<?php

namespace App\Status\Mapping;

use Psr\Log\LoggerInterface;
use WeavingTheWeb\Bundle\ApiBundle\Entity\Status;
use WeavingTheWeb\Bundle\ApiBundle\Repository\StatusRepository;
use WeavingTheWeb\Bundle\TwitterBundle\Api\Accessor;

class RefreshStatusMapping implements MappingAwareInterface
{
    /**
     * @var Accessor
     */
    private $accessor;

    /**
     * @var LoggerInterface
     */
    public $logger;

    /**
     * @var StatusRepository
     */
    public $statusRepository;

    /**
     * @var array
     */
    private $oauthTokens;

    public function __construct(Accessor $accessor)
    {
        $this->accessor = $accessor;
    }

    /**
     * @param $oauthTokens
     */
    public function setOAuthTokens($oauthTokens) {
        $this->oauthTokens = $oauthTokens;
        $this->setupAccessor($oauthTokens);
    }

    /**
     * @param $oauthTokens
     */
    protected function setupAccessor($oauthTokens)
    {
        $this->accessor->setUserToken($oauthTokens['token']);
        $this->accessor->setUserSecret($oauthTokens['secret']);

        if (array_key_exists('consumer_token', $oauthTokens)) {
            $this->accessor->setConsumerKey($oauthTokens['consumer_token']);
            $this->accessor->setConsumerSecret($oauthTokens['consumer_secret']);
        }
    }

    /**
     * @param Status $status
     * @return Status
     */
    public function apply(Status $status): Status {
        $apiDocument = $this->accessor->showStatus($status->getStatusId());
        $this->statusRepository->saveStatuses(
            [$apiDocument],
            $status->getIdentifier(),
            $status->getAggregates()->first(),
            $this->logger
        );

        return $this->statusRepository->findOneBy(['id' => $status->getId()]);
    }
}

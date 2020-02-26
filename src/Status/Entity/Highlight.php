<?php

namespace App\Status\Entity;

use App\Membership\Entity\MemberInterface;
use Predis\Configuration\Option\Aggregate;
use App\Domain\Status\StatusInterface;

class Highlight
{
    private $id;

    /**
     * @var \DateTime
     */
    private $publicationDateTime;

    /**
     * @var \App\Api\Entity\Status
     */
    private $status;

    /**
     * App\Membership\Entity\Member
     */
    private $member;

    /**
     * @var boolean
     */
    private $isRetweet;

    /**
     * @var Aggregate
     */
    private $aggregate;

    /**
     * @var string
     */
    private $aggregateName;

    /**
     * @var \DateTime
     */
    private $retweetedStatusPublicationDate;

    /**
     * @var int
     */
    private $totalRetweets;

    /**
     * @var int
     */
    private $totalFavorites;

    public function __construct(
        MemberInterface $member,
        StatusInterface $status,
        \DateTime $publicationDateTime
    ) {
        $this->publicationDateTime = $publicationDateTime;
        $this->member = $member;
        $this->status = $status;
    }
}

<?php

namespace App\Popularity\Entity;

use App\Domain\Status\StatusInterface;

class StatusPopularity
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var StatusInterface
     */
    private $status;

    /**
     * @var integer
     */
    private $totalRetweets;

    /**
     * @var integer
     */
    private $totalFavorites;

    /**
     * @var \DateTime
     */
    private $checkedAt;
}

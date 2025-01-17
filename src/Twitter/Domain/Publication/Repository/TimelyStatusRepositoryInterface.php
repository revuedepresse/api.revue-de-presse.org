<?php
declare(strict_types=1);

namespace App\Twitter\Domain\Publication\Repository;

use App\Ownership\Domain\Entity\MembersListInterface;
use App\Twitter\Domain\Publication\StatusInterface;

interface TimelyStatusRepositoryInterface
{
    public function fromAggregatedStatus(
        StatusInterface $status,
        ?MembersListInterface $aggregaste = null
    );
}

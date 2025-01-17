<?php
declare(strict_types=1);

namespace App\Twitter\Infrastructure\Http\Accessor;

use App\Twitter\Domain\Curation\CollectionStrategyInterface;
use App\Membership\Domain\Entity\MemberInterface;

interface StatusAccessorInterface
{
    public function ensureMemberHavingNameExists(string $memberName): MemberInterface;

    public function ensureMemberHavingIdExists(string $id): ?MemberInterface;
}

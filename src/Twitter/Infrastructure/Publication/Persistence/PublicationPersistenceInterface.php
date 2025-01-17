<?php
declare(strict_types=1);

namespace App\Twitter\Infrastructure\Publication\Persistence;

use App\Ownership\Domain\Entity\MembersListInterface;
use App\Twitter\Infrastructure\Http\AccessToken\AccessToken;
use App\Twitter\Infrastructure\Operation\Collection\CollectionInterface;

interface PublicationPersistenceInterface
{
    /**
     * @throws \App\Twitter\Infrastructure\Exception\NotFoundMemberException
     */
    public function persistStatusPublications(
        array $statuses,
        AccessToken $identifier,
        MembersListInterface $list = null
    ): CollectionInterface;
}

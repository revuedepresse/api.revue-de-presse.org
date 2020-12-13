<?php
declare(strict_types=1);

namespace App\Infrastructure\DependencyInjection\Collection;

use App\Domain\Curation\Repository\PublishersListCollectedEventRepositoryInterface;

trait PublishersListCollectedEventRepositoryTrait
{
    private PublishersListCollectedEventRepositoryInterface $publishersListCollectedEventRepository;

    public function setPublishersListCollectedEventRepository(
        PublishersListCollectedEventRepositoryInterface $publishersListCollectedEventRepository
    ): self {
        $this->publishersListCollectedEventRepository = $publishersListCollectedEventRepository;

        return $this;
    }
}
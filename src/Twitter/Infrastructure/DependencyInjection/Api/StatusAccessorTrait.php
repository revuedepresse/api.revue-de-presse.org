<?php
declare(strict_types=1);

namespace App\Twitter\Infrastructure\DependencyInjection\Api;

use App\Twitter\Infrastructure\Http\Accessor\StatusAccessorInterface;

trait StatusAccessorTrait
{
    private StatusAccessorInterface $statusAccessor;

    public function setStatusAccessor(StatusAccessorInterface $statusAccessor): self
    {
        $this->statusAccessor = $statusAccessor;

        return $this;
    }
}
